<div data-control="toolbar loader-container">
    <a
        href="<?= Backend::url('majos/conference/papersubmissions/create') ?>"
        class="btn btn-primary">
        <i class="icon-plus"></i>
        <?= __("New :name", ['name' => 'Paper Submission']) ?>
    </a>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-secondary"
        data-request="onDownloadAll"
        data-request-confirm="Are you sure you want to download the selected papers?"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-download"></i>
        <?= __("Download Selected as ZIP") ?>
    </button>

    <div class="toolbar-divider"></div>

    <button
        class="btn btn-danger"
        data-request="onDelete"
        data-request-message="<?= __("Deleting...") ?>"
        data-request-confirm="<?= __("Are you sure?") ?>"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-trash"></i>
        <?= __("Delete") ?>
    </button>

    <?php if ($latestZip = Session::get('majos_latest_paper_zip')): ?>
        <a
            href="<?= Backend::url('majos/conference/papersubmissions/getdownload/' . $latestZip) ?>"
            class="btn btn-default oc-icon-file-archive-o"
            style="color: #FFB703;">
            <?= __("Download Latest ZIP") ?>
        </a>
    <?php endif; ?>
</div>