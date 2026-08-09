<?= Form::open(['class' => 'form-tabs']) ?>

<div class="layout">
    <div class="layout-row">
        <div class="padded-container">
            <div class="scoreboard" style="margin-bottom: 24px;">
                <div class="scoreboard-item title-value">
                    <h4>Status</h4>
                    <p><?= $this->formGetWidget()->renderField('status') ?></p>
                </div>
                <div class="scoreboard-item title-value">
                    <h4>Submitted</h4>
                    <p><?= e($model->created_at->format('F j, Y H:i')) ?></p>
                </div>
                <div class="scoreboard-item title-value">
                    <h4>Category</h4>
                    <p><span class="badge badge-info"><?= e($model->category) ?></span></p>
                </div>
                <div class="scoreboard-item title-value">
                    <h4>Presentation</h4>
                    <p><?= e($model->presentation_type) ?></p>
                </div>
            </div>

            <?= $this->formGetWidget()->render(['ui' => 'readonly']) ?>

            <div class="form-buttons" style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #eee;">
                <a href="<?= Backend::url('majos/conference/submissions') ?>" class="btn btn-default">
                    <i class="icon-arrow-left"></i> Back to list
                </a>
            </div>
        </div>
    </div>
</div>

<?= Form::close() ?>
