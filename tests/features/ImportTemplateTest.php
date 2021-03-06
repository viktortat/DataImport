<?php

use App\User;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LaravelEnso\TestHelper\app\Traits\SignIn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaravelEnso\DataImport\app\Models\ImportTemplate;

class ImportTemplateTest extends TestCase
{
    use RefreshDatabase, SignIn;

    const IMPORT_DIRECTORY = 'testImportDirectory'.DIRECTORY_SEPARATOR;
    const PATH = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR
        .'testFiles'.DIRECTORY_SEPARATOR;
    const TEMPLATE_FILE = 'owners_import_file.xlsx';
    const TEMPLATE_TEST_FILE = 'owners_import_test_file.xlsx';

    protected function setUp()
    {
        parent::setUp();

        // $this->withoutExceptionHandling();
        config()->set('enso.config.paths.imports', self::IMPORT_DIRECTORY);
        $this->signIn(User::first());
    }

    /** @test */
    public function getTemplate()
    {
        $this->uploadTemplateFile();

        $this->get(route('import.getTemplate', ['owners'], false))
            ->assertStatus(200);
    }

    /** @test */
    public function uploadTemplate()
    {
        $this->post(
            route('import.uploadTemplate', ['owners'], false),
            ['file' => $this->getTemplateUploadedFile()]
        )->assertStatus(201);

        $importTemplate = ImportTemplate::whereOriginalName(
            self::TEMPLATE_TEST_FILE
        )->first();

        Storage::assertExists(
            self::IMPORT_DIRECTORY.$importTemplate->saved_name
        );

        $this->assertNotNull($importTemplate);

        $this->cleanUp();
    }

    /** @test */
    public function downloadTemplate()
    {
        $importTemplate = $this->uploadTemplateFile();

        $this->get(route('import.downloadTemplate', [$importTemplate->id], false))
            ->assertStatus(200)
            ->assertHeader(
                'content-disposition',
                'attachment; filename='.self::TEMPLATE_TEST_FILE
            );

        $this->cleanUp();
    }

    /** @test */
    public function deleteTemplate()
    {
        $importTemplate = $this->uploadTemplateFile();

        Storage::assertExists(
            self::IMPORT_DIRECTORY.$importTemplate->saved_name
        );

        $this->assertNotNull($importTemplate);

        $this->delete(route('import.deleteTemplate', [$importTemplate->id], false))
            ->assertStatus(200);

        $this->assertNull($importTemplate->fresh());

        Storage::assertMissing(
            self::IMPORT_DIRECTORY.$importTemplate->saved_name
        );

        $this->cleanUp();
    }

    private function uploadTemplateFile()
    {
        $this->post(
            route('import.uploadTemplate', ['owners'], false),
            ['file' => $this->getTemplateUploadedFile()]
        );

        return ImportTemplate::whereOriginalName(
            self::TEMPLATE_TEST_FILE
        )->first();
    }

    private function getTemplateUploadedFile()
    {
        \File::copy(
            self::PATH.self::TEMPLATE_FILE,
            self::PATH.self::TEMPLATE_TEST_FILE
        );

        return new UploadedFile(
            self::PATH.self::TEMPLATE_TEST_FILE,
            self::TEMPLATE_TEST_FILE,
            null,
            null,
            null,
            true
        );
    }

    private function cleanUp()
    {
        Storage::deleteDirectory(self::IMPORT_DIRECTORY);
    }
}
