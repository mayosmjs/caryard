<div data-control="toolbar loader-container">

    <button
        class="btn btn-warning"
        data-control="popup"
        data-handler="onLoadAssignReviewerForm"
        data-list-checked-trigger
        data-list-checked-request
        disabled>
        <i class="icon-user-plus"></i>
        <?= __("Assign for Review") ?>
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
        <i class="icon-delete"></i>
        <?= __("Delete") ?>
    </button>
</div>
