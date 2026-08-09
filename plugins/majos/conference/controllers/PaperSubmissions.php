<?php namespace Majos\Conference\Controllers;

use BackendMenu;
use Backend\Classes\Controller;
use Majos\Conference\Models\PaperSubmission;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use ZipArchive;
use Log;

/**
 * Paper Submissions Backend Controller
 */
class PaperSubmissions extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['majos.conference.manage_papers'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Majos.Conference', 'conference', 'papers');
    }

    /**
     * AJAX handler for bulk download
     * Downloads ALL files if none are selected, using reference numbers as filenames.
     */
    public function onDownloadAll()
    {
        $selectedIds = post('checked');
        
        // If nothing is selected, we download ALL papers in the database
        if (!$selectedIds || !is_array($selectedIds) || !count($selectedIds)) {
            $submissions = PaperSubmission::all();
        } else {
            $submissions = PaperSubmission::whereIn('id', $selectedIds)->get();
        }

        if ($submissions->isEmpty()) {
            throw new \ApplicationException('No submissions found to download.');
        }

        // Delete previous zips to keep storage clean
        $this->cleanupOldZips();

        $zipName = 'papers_full_export_' . time() . '.zip';
        $zipPath = storage_path('app/' . $zipName);
        $filesAdded = 0;

        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive;
            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                foreach ($submissions as $submission) {
                    $sourcePath = storage_path('app/papers/' . $submission->stored_filename);
                    if (file_exists($sourcePath)) {
                        // Use Reference Number for the filename inside the ZIP
                        $zipNameInside = $submission->reference_number . '.pdf';
                        $zip->addFile($sourcePath, $zipNameInside);
                        $filesAdded++;
                    }
                }
                $zip->close();
            }
        } else {
            $tempDir = storage_path('app/temp_zip_' . time());
            mkdir($tempDir, 0755, true);
            foreach ($submissions as $submission) {
                $sourcePath = storage_path('app/papers/' . $submission->stored_filename);
                if (file_exists($sourcePath)) {
                    $zipNameInside = $submission->reference_number . '.pdf';
                    copy($sourcePath, $tempDir . '/' . $zipNameInside);
                    $filesAdded++;
                }
            }
            if ($filesAdded > 0) {
                $command = sprintf('cd %s && zip -j %s *', escapeshellarg($tempDir), escapeshellarg($zipPath));
                exec($command);
            }
            $this->recursiveRemoveDir($tempDir);
        }

        if ($filesAdded === 0) {
            throw new \ApplicationException('No physical files were found on the server.');
        }

        // Store latest zip in session for the toolbar link
        Session::put('majos_latest_paper_zip', $zipName);

        return \Redirect::to(\Backend::url('majos/conference/papersubmissions/getdownload/' . $zipName));
    }

    /**
     * Non-AJAX route to serve the generated ZIP
     */
    public function getdownload($name)
    {
        if (!preg_match('/^papers_full_export_\d+\.zip$/', $name) && !preg_match('/^papers_export_\d+\.zip$/', $name)) {
            return Response::make('Invalid file request.', 403);
        }

        $path = storage_path('app/' . $name);

        if (!file_exists($path)) {
            return Response::make('File not found.', 404);
        }

        return Response::download($path);
    }

    /**
     * Clean up all papers_*export_*.zip files
     */
    protected function cleanupOldZips()
    {
        $files = glob(storage_path('app/papers_*export_*.zip'));
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        Session::forget('majos_latest_paper_zip');
    }

    private function recursiveRemoveDir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        $this->recursiveRemoveDir($dir . "/" . $object);
                    else
                        unlink($dir . "/" . $object);
                }
            }
            rmdir($dir);
        }
    }
}
