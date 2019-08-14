<div class="row display-flex">
    <?php
        $field = new Uccello\Core\Models\Field();
        $field->name = 'test1';
        $field->uitype_id = uitype('text')->id;
    ?>
    @include('uccello::modules.default.uitypes.designer.text')
</div>