<?php
$db = Database::getInstance();

$documents = $db->fetchAll("SELECT * FROM documents ORDER BY upload_date DESC");

$uploadDir = file_exists(__DIR__ . '/../../includes/env-loader.php')
    ? __DIR__ . '/../../uploads/documents/'
    : __DIR__ . '/../../../uploads/documents/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Upload Documents</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" class="mb-4">
            <input type="hidden" name="action" value="upload_document">
            <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">

            <div class="mb-3">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. 2025 Official Rulebook">
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2" placeholder="Brief description of this document"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">File *</label>
                <input type="file" name="document_file" class="form-control" required>
                <small class="text-muted">Accepted types: PDF, DOC, DOCX, XLS, XLSX, TXT (max 10MB)</small>
            </div>

            <div class="form-check mb-3">
                <input type="checkbox" name="is_public" value="1" class="form-check-input" id="isPublic" checked>
                <label class="form-check-label" for="isPublic">Visible to coaches</label>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Document
            </button>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Existing Documents</h3>
    </div>
    <div class="card-body">
        <?php if (empty($documents)): ?>
            <div class="alert alert-info">No documents uploaded yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>File</th>
                            <th>Size</th>
                            <th>Public</th>
                            <th>Uploaded</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc): ?>
                        <tr>
                            <td>
                                <strong><?php echo sanitize($doc['title']); ?></strong>
                                <?php if (!empty($doc['description'])): ?>
                                    <br><small class="text-muted"><?php echo sanitize($doc['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../../download-document.php?id=<?php echo (int) $doc['document_id']; ?>" target="_blank">
                                    <?php echo sanitize($doc['original_filename']); ?>
                                </a>
                            </td>
                            <td><?php echo $doc['file_size'] ? round($doc['file_size'] / 1024, 1) . ' KB' : 'N/A'; ?></td>
                            <td>
                                <?php if ($doc['is_public']): ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('n/j/Y g:i A', strtotime($doc['upload_date'])); ?></td>
                            <td>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this document?');">
                                    <input type="hidden" name="action" value="delete_document">
                                    <input type="hidden" name="csrf_token" value="<?php echo Auth::generateCSRFToken(); ?>">
                                    <input type="hidden" name="document_id" value="<?php echo (int) $doc['document_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
