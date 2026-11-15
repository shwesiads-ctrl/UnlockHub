<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileSupportTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
        $app = parent::createApplication();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('These tests require the isolated in-memory SQLite database.');
        }
        return $app;
    }

    private function image(int $width, int $height): UploadedFile
    {
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\x31\x5e\xfb", $width), $height)))
            .$chunk('IEND', '');
        return UploadedFile::fake()->createWithContent('photo.png', $png);
    }

    public function test_profile_photos_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create()->fresh();
        $this->actingAs($user)->post('/account/profile/cover', ['cover' => $this->image(600, 180)])->assertRedirect();
        $old = $user->fresh()->cover_path;
        $this->assertNotNull($old);
        Storage::disk('public')->assertExists($old);
        $this->post('/account/profile/cover', ['cover' => $this->image(800, 240)])->assertRedirect();
        Storage::disk('public')->assertMissing($old);
        $this->post('/account/profile/avatar', ['avatar' => $this->image(80, 80)])->assertRedirect();
        $this->assertNotNull($user->fresh()->avatar_path);
        $this->post('/account/profile/cover', ['cover' => UploadedFile::fake()->createWithContent('bad.svg', '<svg/>')])->assertSessionHasErrors('cover');
        $this->delete('/account/profile/cover')->assertRedirect();
        $this->delete('/account/profile/avatar')->assertRedirect();
        $this->assertNull($user->fresh()->cover_path);
        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_only_admin_can_grant_a_blue_mark(): void
    {
        $user = User::factory()->create()->fresh();
        $admin = User::factory()->admin()->create()->fresh();
        $this->actingAs($user)->put('/admin/users/'.$user->id.'/verification', ['is_verified' => 1])->assertForbidden();
        $this->assertFalse($user->fresh()->is_verified);
        $this->actingAs($admin)->put('/admin/users/'.$user->id.'/verification', ['is_verified' => 1])->assertRedirect();
        $this->assertTrue($user->fresh()->is_verified);
        $this->actingAs($user->fresh())->get('/account/profile')->assertOk()->assertSee('Admin verified account');
    }

    public function test_support_ownership_staff_replies_and_closed_tickets(): void
    {
        $user = User::factory()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $admin = User::factory()->admin()->create()->fresh();
        $this->actingAs($user)->post('/account/support', ['subject' => 'Payment help', 'message' => 'Please check my payment', 'channel' => 'ticket'])->assertRedirect();
        $ticket = SupportTicket::where('user_id', $user->id)->firstOrFail();
        $this->actingAs($other)->get('/account/support/'.$ticket->id)->assertNotFound();
        $this->getJson('/account/support/'.$ticket->id.'/messages')->assertNotFound();
        $this->post('/account/support/'.$ticket->id.'/messages', ['message' => 'Not mine'])->assertNotFound();
        $this->get('/admin/support')->assertForbidden();
        $this->actingAs($admin)->get('/admin/support')->assertOk()->assertSee('Payment help');
        $this->post('/admin/support/'.$ticket->id.'/messages', ['message' => 'We are checking.'])->assertRedirect();
        $this->actingAs($user)->getJson('/account/support/'.$ticket->id.'/messages?after=0')->assertOk()->assertJsonFragment(['body' => 'We are checking.', 'is_staff' => true]);
        $this->actingAs($admin)->put('/admin/support/'.$ticket->id.'/status', ['status' => 'closed'])->assertRedirect();
        $this->actingAs($user)->post('/account/support/'.$ticket->id.'/messages', ['message' => 'Reply'])->assertStatus(422);
    }

    public function test_live_chat_resumes_without_creating_duplicate_threads(): void
    {
        $user = User::factory()->create()->fresh();
        $this->actingAs($user);
        foreach (['Hello team', 'I need order help'] as $message) {
            $this->post('/account/support', ['subject' => 'Live chat', 'channel' => 'chat', 'message' => $message])->assertRedirect();
        }
        $this->assertSame(1, SupportTicket::where('user_id', $user->id)->where('channel', 'chat')->count());
        $ticket = SupportTicket::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, $ticket->messages()->count());
        $this->get('/account/support/'.$ticket->id)->assertOk()->assertSee('data-support-conversation', false);
    }

    public function test_ticket_details_and_attachments_are_private(): void
    {
        Storage::fake('local');
        $user = User::factory()->create()->fresh();
        $other = User::factory()->create()->fresh();
        $admin = User::factory()->admin()->create()->fresh();
        $this->actingAs($user)->post('/account/support', [
            'channel' => 'ticket', 'subject' => 'Wallet top-up issue', 'message' => 'Please review the attached screenshot.',
            'category' => 'payment', 'priority' => 'high', 'attachment' => $this->image(80, 80),
        ])->assertRedirect();
        $ticket = SupportTicket::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('payment', $ticket->category);
        $this->assertSame('high', $ticket->priority);
        $message = $ticket->messages()->firstOrFail();
        Storage::disk('local')->assertExists($message->attachment_path);
        $path = '/account/support/'.$ticket->id.'/attachments/'.$message->id;
        $this->get($path)->assertOk()->assertDownload('photo.png');
        $this->actingAs($other)->get($path)->assertNotFound();
        $this->actingAs($admin)->get('/admin/support/'.$ticket->id.'/attachments/'.$message->id)->assertOk();
        $this->actingAs($user)->post('/account/support', [
            'channel' => 'ticket', 'subject' => 'Bad file', 'message' => 'Invalid upload',
            'attachment' => UploadedFile::fake()->createWithContent('script.html', '<script>alert(1)</script>'),
        ])->assertSessionHasErrors('attachment');
    }

    public function test_another_users_order_cannot_be_linked(): void
    {
        $user = User::factory()->create()->fresh();
        $this->actingAs($user)->post('/account/support', [
            'channel' => 'ticket', 'subject' => 'Order question', 'message' => 'Help with this order',
            'category' => 'order', 'order_no' => 'UH-NOT-MY-ORDER',
        ])->assertSessionHasErrors('order_no');
        $this->assertSame(0, SupportTicket::where('user_id', $user->id)->count());
    }

    public function test_async_messages_retry_once_and_preserve_status_and_attachments(): void
    {
        Storage::fake('local');
        $user = User::factory()->create()->fresh();
        $admin = User::factory()->admin()->create()->fresh();
        $ticket = SupportTicket::create(['user_id' => $user->id, 'subject' => 'Live chat', 'channel' => 'chat']);
        $path = '/account/support/'.$ticket->id.'/messages';
        $token = (string) \Illuminate\Support\Str::uuid();
        $this->actingAs($user)->post($path, [
            'message' => 'Here is my screenshot', 'client_token' => $token, 'after' => 0,
            'attachment' => $this->image(80, 80),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonFragment(['body' => 'Here is my screenshot', 'is_staff' => false]);
        $this->postJson($path, ['message' => 'Here is my screenshot', 'client_token' => $token, 'after' => 0])->assertOk();
        $this->assertSame(1, $ticket->messages()->count());
        $this->assertCount(1, Storage::disk('local')->allFiles('support'));
        $first = $ticket->messages()->firstOrFail();
        $this->getJson($path.'?after='.$first->id)->assertOk()->assertJsonCount(0, 'messages');
        $this->actingAs($admin)->postJson('/admin/support/'.$ticket->id.'/messages', ['message' => 'Checking now', 'after' => $first->id])
            ->assertOk()->assertJsonPath('status', 'waiting')->assertJsonFragment(['body' => 'Checking now', 'is_staff' => true]);
        $this->actingAs($user)->postJson($path, ['message' => 'Thank you', 'client_token' => (string) \Illuminate\Support\Str::uuid()])
            ->assertOk()->assertJsonPath('status', 'open');
        $this->postJson($path, ['message' => '', 'client_token' => (string) \Illuminate\Support\Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->assertSame(3, $ticket->messages()->count());
    }

    public function test_reopened_chat_accepts_messages_and_waiting_chat_resumes(): void
    {
        $user = User::factory()->create()->fresh();
        $admin = User::factory()->admin()->create()->fresh();
        $ticket = SupportTicket::create(['user_id' => $user->id, 'subject' => 'Live chat', 'channel' => 'chat', 'status' => 'waiting']);
        $this->actingAs($user)->post('/account/support', ['subject' => 'Live chat', 'channel' => 'chat', 'message' => 'Following up'])->assertRedirect();
        $this->assertSame(1, SupportTicket::where('user_id', $user->id)->count());
        $this->assertSame('open', $ticket->fresh()->status);
        $this->actingAs($admin)->put('/admin/support/'.$ticket->id.'/status', ['status' => 'resolved'])->assertRedirect();
        $this->actingAs($user)->postJson('/account/support/'.$ticket->id.'/messages', ['message' => 'Hello'])->assertUnprocessable();
        $this->actingAs($admin)->put('/admin/support/'.$ticket->id.'/status', ['status' => 'open'])->assertRedirect();
        $this->actingAs($user)->postJson('/account/support/'.$ticket->id.'/messages', ['message' => 'Back again'])->assertOk();
    }

    public function test_support_staff_permissions_are_enforced_for_live_chat(): void
    {
        $user = User::factory()->create()->fresh();
        $support = User::factory()->admin()->create(['staff_role' => 'support'])->fresh();
        $editor = User::factory()->admin()->create(['staff_role' => 'editor'])->fresh();
        $ticket = SupportTicket::create(['user_id' => $user->id, 'subject' => 'Account help', 'channel' => 'chat']);
        $this->actingAs($editor)->get('/admin/support')->assertForbidden();
        $this->postJson('/admin/support/'.$ticket->id.'/messages', ['message' => 'Forbidden reply'])->assertForbidden();
        $this->actingAs($support)->get('/admin/support')->assertOk();
        $this->postJson('/admin/support/'.$ticket->id.'/messages', ['message' => 'Support staff reply'])->assertOk();
    }
}
