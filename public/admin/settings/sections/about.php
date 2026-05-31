<?php
/**
 * About Page Settings Section
 */
?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_about">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

            <div class="mb-3">
                <label class="form-label">About Page Content</label>
                <textarea id="about_content_editor" name="about_page_content" class="form-control" rows="16"><?php echo htmlspecialchars($aboutPageContent, ENT_QUOTES, 'UTF-8'); ?></textarea>
                <div class="form-text">
                    This content is displayed on the public <a href="../../../about.php" target="_blank">About page</a>.
                </div>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save About Page
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#about_content_editor',
    plugins: 'lists link',
    toolbar: 'bold italic underline | h2 h3 | bullist numlist | link | removeformat',
    menubar: false,
    height: 400,
    promotion: false,
    branding: false
});
</script>
