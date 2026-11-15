<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Models\User;
use App\Support\SafeName;
use App\Support\Spreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'admin', 'staff_role' => 'owner'])->save();

        return $u->fresh();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_unknown_host_header_does_not_change_links(): void
    {
        config(['app.url' => 'https://unlockhub.test']);
        $this->get('http://evil.example/login')->assertOk();

        $this->assertStringStartsWith('https://unlockhub.test', url('/reset-password/abc'));
        $this->assertStringStartsWith('https://unlockhub.test', route('password.reset', 'abc'));
    }

    public function test_downloads_reject_runnable_files_and_are_stored_privately(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $owner = $this->owner();

        foreach (['shell.php', 'tool.php.zip', '.htaccess', 'page.html', 'x.svg'] as $bad) {
            $this->actingAs($owner)->post(route('admin.downloads.store'), [
                'title' => 'Bad', 'file' => UploadedFile::fake()->create($bad, 10),
            ])->assertSessionHasErrors('file');
        }
        $this->assertSame(0, Download::count());

        $this->actingAs($owner)->post(route('admin.downloads.store'), [
            'title' => 'Driver', 'file' => UploadedFile::fake()->create('driver-v1.2.zip', 10), 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $d = Download::firstOrFail();
        Storage::disk('local')->assertExists($d->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->get(route('downloads.get', $d))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_csv_cells_cannot_become_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://x\")", Spreadsheet::safeCell('=HYPERLINK("http://x")'));
        $this->assertSame("'@SUM(A1)", Spreadsheet::safeCell('@SUM(A1)'));
        $this->assertSame('-1500', Spreadsheet::safeCell('-1500'));
        $this->assertSame(-1500, Spreadsheet::safeCell(-1500));
        $this->assertSame('356938035643809', Spreadsheet::safeCell('356938035643809'));
        $this->assertStringContainsString("'=cmd", Spreadsheet::csv([['=cmd|calc']]));
    }

    public function test_crafted_xlsx_column_is_ignored(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="S" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>IMEI</t></is></c><c r="ZZZZZZ1" t="inlineStr"><is><t>boom</t></is></c></row></sheetData></worksheet>');
        $zip->close();

        $rows = Spreadsheet::readXlsx($path, 10);
        $this->assertSame([['IMEI']], $rows);
        @unlink($path);
    }

    public function test_staff_without_2fa_is_sent_to_security_when_required(): void
    {
        \App\Models\Setting::put('security.staff_2fa', '1');
        $this->actingAs($this->owner())->get('/admin')->assertRedirect(route('account.security'));

        \App\Models\Setting::put('security.staff_2fa', '0');
        $this->actingAs($this->owner())->get('/admin')->assertOk();
    }

    public function test_safe_header_file_names(): void
    {
        $this->assertSame('a_b_.png', SafeName::header("a\"b\n.png"));
        $this->assertTrue(SafeName::isDangerous('x.PHP'));
        $this->assertFalse(SafeName::isDangerous('UnlockTool_2026.11.zip'));
    }
}
