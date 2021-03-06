<?php

use App\User;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LaravelEnso\TestHelper\app\Traits\SignIn;
use LaravelEnso\DataImport\app\Models\DataImport;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DataImportTest extends TestCase
{
    use RefreshDatabase, SignIn;

    const IMPORT_DIRECTORY = 'testImportDirectory'.DIRECTORY_SEPARATOR;
    const PATH = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR
        .'testFiles'.DIRECTORY_SEPARATOR;
    const OWNERS_IMPORT_FILE = 'owners_import_file.xlsx';
    const OWNERS_IMPORT_TEST_FILE = 'owners_import_test_file.xlsx';

    protected function setUp()
    {
        parent::setUp();

        // $this->withoutExceptionHandling();

        config()->set('enso.config.paths.imports', self::IMPORT_DIRECTORY);

        $this->signIn(User::first());
    }

    /** @test */
    public function index()
    {
        $this->get(route('import.index', [], false))
            ->assertStatus(200);
    }

    /** @test */
    public function getSummary()
    {
        $this->importOwnersFile();

        $dataImport = DataImport::whereOriginalName(
            self::OWNERS_IMPORT_TEST_FILE
        )->first();

        $this->get(route('import.getSummary', [$dataImport->id], false))
            ->assertStatus(200);
    }

    /** @test */
    public function can_import()
    {
        $uploadedFile = $this->getOwnersImportUploadedFile();

        $this->post(route('import.run', ['owners'], false), [
            'file' => $uploadedFile
        ])->assertStatus(200)
            ->assertJsonFragment(['successful' => 2]);

        $dataImport = DataImport::whereOriginalName(
            self::OWNERS_IMPORT_TEST_FILE
        )->first();

        $this->assertNotNull($dataImport);

        $this->assertNotNull(
            config('enso.config.ownerModel')::whereName('ImportTestName')
                ->first()
        );

        Storage::assertExists(
            self::IMPORT_DIRECTORY.$dataImport->saved_name
        );

        $this->cleanUp();
    }

    /** @test */
    public function download()
    {
        $this->importOwnersFile();

        $dataImport = DataImport::whereOriginalName(
            self::OWNERS_IMPORT_TEST_FILE
        )->first();

        $this->get(route('import.download', [$dataImport->id], false))
            ->assertStatus(200)
            ->assertHeader(
                'content-disposition',
                'attachment; filename='.self::OWNERS_IMPORT_TEST_FILE
            );

        $this->cleanUp();
    }

    /** @test */
    public function destroy()
    {
        $this->importOwnersFile();

        $dataImport = DataImport::whereOriginalName(
            self::OWNERS_IMPORT_TEST_FILE
        )->first();

        Storage::assertExists(self::IMPORT_DIRECTORY.$dataImport->saved_name);

        $this->assertNotNull($dataImport);

        $this->delete(route('import.destroy', [$dataImport->id], false))
            ->assertStatus(200);

        $this->assertNull($dataImport->fresh());
        Storage::assertMissing(
            self::IMPORT_DIRECTORY.$dataImport->saved_name
        );

        $this->cleanUp();
    }

    private function importOwnersFile()
    {
        $uploadedFile = $this->getOwnersImportUploadedFile();

        $this->post(route('import.run', ['owners'], false), [
            'file' => $uploadedFile
        ]);

        return $uploadedFile;
    }

    private function getOwnersImportUploadedFile()
    {
        \File::copy(
            self::PATH.self::OWNERS_IMPORT_FILE,
            self::PATH.self::OWNERS_IMPORT_TEST_FILE
        );

        return new UploadedFile(
            self::PATH.self::OWNERS_IMPORT_TEST_FILE,
            self::OWNERS_IMPORT_TEST_FILE,
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
