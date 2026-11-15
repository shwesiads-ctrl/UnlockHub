<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(TicketAttachment::DISK);
        $this->withoutVite();
    }

    private function ticketFor(User $user): Ticket
    {
        return app(TicketService::class)->open($user, [
            'category' => 'order', 'subject' => 'Help', 'message' => 'My order is late',
        ], [UploadedFile::fake()->image('proof.png')]);
    }

    public function test_user_cannot_see_someone_elses_ticket_or_attachment(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        $ticket = $this->ticketFor($alice);
        $file = TicketAttachment::first();

        $this->actingAs($bob)->get(route('account.tickets.show', $ticket))->assertNotFound();
        $this->actingAs($bob)->get(route('account.tickets.attachment', [$ticket, $file]))->assertNotFound();
        $this->actingAs($bob)->post(route('account.tickets.reply', $ticket), ['message' => 'hi'])->assertNotFound();

        $this->actingAs($alice)->get(route('account.tickets.attachment', [$ticket, $file]))->assertOk();
    }

    public function test_attachment_must_belong_to_the_ticket_in_the_url(): void
    {
        $alice = $this->customer();
        $bob = $this->customer();
        $aliceTicket = $this->ticketFor($alice);
        $bobTicket = $this->ticketFor($bob);
        $bobFile = TicketAttachment::whereHas('message', fn ($q) => $q->where('ticket_id', $bobTicket->id))->first();

        $this->actingAs($alice)->get(route('account.tickets.attachment', [$aliceTicket, $bobFile]))->assertNotFound();
    }

    public function test_internal_notes_are_hidden_from_the_customer(): void
    {
        $alice = $this->customer();
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'admin', 'staff_role' => 'support'])->save();
        $ticket = $this->ticketFor($alice);

        app(TicketService::class)->staffReply($ticket, $staff, 'SECRET-NOTE', [UploadedFile::fake()->create('internal.pdf', 10, 'application/pdf')], true, null);
        $internalFile = TicketAttachment::latest('id')->first();

        $this->actingAs($alice)->get(route('account.tickets.show', $ticket))->assertOk()->assertDontSee('SECRET-NOTE');
        $this->actingAs($alice)->get(route('account.tickets.attachment', [$ticket, $internalFile]))->assertNotFound();
    }

    public function test_statuses_and_reopen_window(): void
    {
        $alice = $this->customer();
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'admin', 'staff_role' => 'support'])->save();
        $svc = app(TicketService::class);
        $ticket = $this->ticketFor($alice);

        $svc->staffReply($ticket, $staff, 'Please send a screenshot', [], false, null);
        $this->assertSame('waiting_customer', $ticket->fresh()->status);

        $svc->customerReply($ticket, $alice, 'Here it is');
        $this->assertSame('in_progress', $ticket->fresh()->status);

        $svc->update($ticket, $staff, ['status' => 'resolved']);
        $this->assertTrue($ticket->fresh()->customerCanReply());

        $ticket->forceFill(['resolved_at' => now()->subDays(Ticket::reopenDays() + 1)])->save();
        $this->assertFalse($ticket->fresh()->customerCanReply());

        $this->artisan('tickets:close-resolved')->assertSuccessful();
        $this->assertSame('closed', $ticket->fresh()->status);
    }
}
