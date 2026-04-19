<?php Block::put('breadcrumb') ?>
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= Backend::url('majos/sellers/invoices') ?>">Invoices</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($this->pageTitle) ?></li>
    </ol>
<?php Block::endPut() ?>

<?php if (!$this->formGetModel()): ?>
    <p>Invoice not found.</p>
<?php else: 
    $model = $this->formGetModel();
?>
    <div class="row">
        <div class="col-md-6">
            <h3>Invoice #<?= e($model->invoice_number) ?></h3>
            <table class="table table-striped">
                <tr>
                    <td><strong>ID:</strong></td>
                    <td><?= e($model->id) ?></td>
                </tr>
                <tr>
                    <td><strong>Transaction:</strong></td>
                    <td><?= e($model->transaction_id) ?></td>
                </tr>
                <tr>
                    <td><strong>Subscription:</strong></td>
                    <td><?= e($model->subscription_id) ?></td>
                </tr>
                <tr>
                    <td><strong>Amount:</strong></td>
                    <td><?= e($model->currency) ?> <?= number_format($model->amount, 2) ?></td>
                </tr>
                <tr>
                    <td><strong>Status:</strong></td>
                    <td><?= e($model->status) ?></td>
                </tr>
                <tr>
                    <td><strong>Description:</strong></td>
                    <td><?= e($model->description) ?></td>
                </tr>
                <tr>
                    <td><strong>Created:</strong></td>
                    <td><?= e($model->created_at) ?></td>
                </tr>
            </table>
            <a href="javascript:;" 
               class="btn btn-primary"
               data-request="onResendReceiptEmail"
               data-request-data="id: <?= $model->id ?>"
               data-load-indicator="Sending..."
               data-attach-loading>
                Resend Receipt Email
            </a>
        </div>
    </div>
<?php endif ?>