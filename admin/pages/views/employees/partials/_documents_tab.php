<?php // /admin/pages/views/employees/partials/_documents_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Documents de l'Employé</h5>
    <a href="<?= route('employees_documents', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-folder-plus"></i> Gérer les Documents</a>
</div>
 <?php if ($documents_stmt->rowCount() > 0): ?>
     <div class="table-responsive">
     <table class="table table-sm table-hover">
     <thead><tr><th>Type</th><th>Titre du Document</th><th>Date d'Upload</th><th>Actions</th></tr></thead><tbody>
     <?php while ($doc = $documents_stmt->fetch(PDO::FETCH_ASSOC)): ?>
     <tr>
         <td><?= ucfirst(htmlspecialchars($doc['document_type'])) ?></td>
         <td><?= htmlspecialchars($doc['title']) ?></td>
         <td><?= formatDate($doc['upload_date'], 'd/m/Y H:i') ?></td>
         <td class="text-end">
             <?php
             $actual_doc_filename = basename($doc['file_path']);
             $baseUrlForLink = '';
             if (defined('APP_LINK')) { $baseUrlForLink = rtrim(APP_LINK, '/'); }
             elseif (defined('BASE_URL')) { $baseUrlForLink = rtrim(BASE_URL, '/'); }
             $documentsWebPath = '/assets/uploads/documents/';
             $doc_filepath_for_link = $baseUrlForLink . $documentsWebPath . $actual_doc_filename;
             ?>
             <a href="#" class="btn btn-sm btn-outline-primary" title="Voir le document" onclick="showPdfPreview('<?= htmlspecialchars($doc_filepath_for_link) ?>'); return false;"><i class="bi bi-eye"></i></a>
             <a href="<?= htmlspecialchars($doc_filepath_for_link) ?>" download="<?= htmlspecialchars($actual_doc_filename) ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="bi bi-download"></i></a>
         </td>
     </tr>
     <?php endwhile; ?>
     </tbody></table></div>
<?php else: ?>
    <div class="alert alert-info">Aucun document n'a été trouvé pour cet employé.</div>
<?php endif; ?>