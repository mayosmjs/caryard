<?php
/** @var array $ids  List of submission IDs being assigned */
?>
<form id="assign-reviewer-form">
<div class="modal-header">
    <h4 class="modal-title">Assign Submission<?= count($ids) > 1 ? 's' : '' ?> for External Review</h4>
    <button type="button" class="close" data-dismiss="modal">&times;</button>
</div>
<div class="modal-body">
    <?php foreach ($ids as $sid): ?>
        <input type="hidden" name="submission_ids[]" value="<?= (int) $sid ?>">
    <?php endforeach; ?>

    <div class="form-group mb-3">
        <label class="control-label">Reviewer Email <span class="required">*</span></label>
        <input type="email"
               name="reviewer_email"
               class="form-control"
               placeholder="reviewer@institution.org"
               required
               autofocus
               autocomplete="off">
        <span class="help-block">
            A secure access link and password will be generated and emailed to this address.
            If this reviewer already exists, their credentials will be reset.
        </span>
    </div>

    <div class="form-group mb-3">
        <label class="control-label">Reviewer Name <span class="text-muted">(optional — used for new reviewers)</span></label>
        <input type="text"
               name="reviewer_name"
               class="form-control"
               placeholder="Dr. Jane Smith">
    </div>

    <div class="form-group mb-3">
        <label class="control-label">Note to Reviewer <span class="text-muted">(optional)</span></label>
        <textarea name="reviewer_note"
                  class="form-control"
                  rows="3"
                  placeholder="Add any instructions, criteria, or context for the reviewer…"></textarea>
    </div>

    <p class="text-muted small mt-2">
        <strong><?= count($ids) ?></strong> submission<?= count($ids) > 1 ? 's' : '' ?> will be assigned.
        The reviewer will receive an email with a password-protected link to the review portal.
        Reviewers do <strong>not</strong> require any backend access.
    </p>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
    <button
        type="submit"
        class="btn btn-warning"
        data-request="onAssignReviewer"
        data-request-form="#assign-reviewer-form"
        data-request-success="$(this).closest('.modal').modal('hide');">
        <i class="icon-send"></i> Assign &amp; Send Review Link
    </button>
</div>
</form>


