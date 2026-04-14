<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('majos/caryard/loanapplications') ?>">Loan Applications</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>

    <div class="form-buttons">
        <div class="loading-indicator-container">
            <?php if ($formModel->status == 'pending'): ?>
                <button
                    type="button"
                    class="btn btn-primary"
                    data-request="onAccept"
                    data-load-indicator="Processing..."
                    data-request-confirm="Are you sure you want to ACCEPT this application and notify the customer?">
                    <i class="icon-check"></i> Accept Application
                </button>
                <button
                    type="button"
                    class="btn btn-danger"
                    data-request="onReject"
                    data-load-indicator="Processing..."
                    data-request-confirm="Are you sure you want to REJECT this application and notify the customer?">
                    <i class="icon-times"></i> Reject Application
                </button>
            <?php else: ?>
                <div class="p-t-xs">
                    <span class="label <?= $formModel->status == 'accepted' ? 'label-success' : 'label-danger' ?> uppercase">
                        Application <?= ucfirst($formModel->status) ?>
                    </span>
                    <span class="m-l-sm text-muted">
                        Status updated on <?= $formModel->updated_at->toDayDateTimeString() ?>
                    </span>
                </div>
            <?php endif ?>
        </div>
    </div>

    <div class="layout-row">
        <?= $this->makePartial('application_preview', ['record' => $formModel]) ?>
    </div>

<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
    <p><a href="<?= Backend::url('majos/caryard/loanapplications') ?>" class="btn btn-default">Return to list</a></p>
<?php endif ?>
