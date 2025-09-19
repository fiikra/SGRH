<?php // /admin/pages/views/employees/partials/_certificates_tab.php ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Historique des Certificats (5 derniers)</h5>
    <div>
        <a href="<?= route('reports_generate', ['nin' => $nin]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-plus"></i> Nouveau Certificat</a>
        <a href="<?= route('reports_history', ['nin' => $nin]) ?>" class="btn btn-sm btn-info ms-1"><i class="bi bi-list-ul"></i> Voir Tout l'Historique</a>
    </div>
</div>
 <?php if ($certificates_stmt->rowCount() > 0): ?>
     <div class="table-responsive">
     <table class="table table-sm table-hover align-middle">
     <thead class="table-light"><tr><th>Type de Certificat</th><th>Numéro de Référence</th><th>Date d'Émission</th><th>Émis par</th><th class="text-end">Actions</th></tr></thead><tbody>
     <?php while ($cert = $certificates_stmt->fetch(PDO::FETCH_ASSOC)): ?>
     <tr><td>
         <?php
             $typeBadgeClass = ['attestation' => 'bg-primary', 'certficate' => 'bg-success', 'attestation_sold' => 'bg-info'];
             $typeLabels = ['attestation' => 'Attestation', 'certficate' => 'Certificat', 'attestation_sold' => 'Attestation de Solde'];
             $certTypeKey = strtolower($cert['certificate_type']);
             $badgeClass = $typeBadgeClass[$certTypeKey] ?? 'bg-secondary';
             $label = $typeLabels[$certTypeKey] ?? ucfirst(htmlspecialchars($cert['certificate_type']));
         ?>
         <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
     </td>
     <td><?= htmlspecialchars($cert['reference_number']) ?></td><td><?= formatDate($cert['issue_date']) ?></td><td><?= htmlspecialchars($cert['prepared_by'] ?? 'N/A') ?></td>
     <td class="text-end">
     <?php
         $actual_cert_filename = basename($cert['generated_filename']);
         $baseUrlForLink = rtrim(APP_LINK ?? BASE_URL ?? '', '/');
         $certificatesWebPath = '/assets/uploads/certificates/';
         $cert_filepath_for_link = $baseUrlForLink . $certificatesWebPath . $actual_cert_filename;
     ?>
     <a href="<?= route('reports_view_certificate', ['ref' => $cert['reference_number']]) ?>" class="btn btn-sm btn-outline-primary" target="_blank" data-bs-toggle="tooltip" title="Voir le PDF"><i class="bi bi-eye"></i></a>
     <a href="<?= htmlspecialchars($cert_filepath_for_link) ?>" download="<?= htmlspecialchars($actual_cert_filename) ?>" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="bi bi-download"></i></a>
     </td></tr>
     <?php endwhile; ?></tbody></table></div>
         <div class="mt-3 text-end">
             <a href="<?= route('reports_history', ['nin' => $nin]) ?>" class="btn btn-sm btn-link">
                 <i class="bi bi-chevron-double-right"></i> Afficher plus de certificats
             </a>
         </div>
<?php else: ?>
         <div class="alert alert-info">Aucun certificat n'a été généré récemment pour cet employé.</div>
<?php endif; ?>