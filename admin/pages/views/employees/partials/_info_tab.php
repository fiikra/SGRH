<?php // /admin/pages/views/employees/partials/_info_tab.php ?>
<div class="row">
    <div class="col-lg-6 mb-4">
        <h5 class="mb-3"><i class="bi bi-person-vcard me-2"></i>Informations Personnelles</h5>
        <dl class="row small">
            <dt class="col-sm-5">NIN:</dt><dd class="col-sm-7"><?= htmlspecialchars($employee['nin']) ?></dd>
            <dt class="col-sm-5">N° Sécurité Sociale (NSS):</dt><dd class="col-sm-7"><?= htmlspecialchars($employee['nss'] ?? 'N/A') ?></dd>
            <dt class="col-sm-5">Date de Naissance:</dt><dd class="col-sm-7"><?= formatDate($employee['birth_date']) ?> (Lieu: <?= htmlspecialchars($employee['birth_place'] ?? 'N/A') ?>)</dd>
            <dt class="col-sm-5">Genre:</dt><dd class="col-sm-7"><?= $employee['gender'] === 'male' ? 'Masculin' : ($employee['gender'] === 'female' ? 'Féminin' : 'Autre') ?></dd>
            <dt class="col-sm-5">Sit. Familiale:</dt><dd class="col-sm-7"><?= htmlspecialchars(ucfirst($employee['marital_status'] ?? 'N/A')) ?></dd>
            <dt class="col-sm-5">Pers. à charge:</dt><dd class="col-sm-7"><?= htmlspecialchars($employee['dependents'] ?? '0') ?></dd>
        </dl>
        
        <h5 class="mt-4 mb-3"><i class="bi bi-house-door me-2"></i>Coordonnées</h5>
        <ul class="list-unstyled small">
            <li><i class="bi bi-envelope me-2 text-muted"></i> <?= htmlspecialchars($employee['email']) ?></li>
            <li class="mt-2"><i class="bi bi-telephone me-2 text-muted"></i> <?= htmlspecialchars($employee['phone']) ?></li>
            <li class="mt-2"><i class="bi bi-geo-alt me-2 text-muted"></i> <?= htmlspecialchars($employee['address'] . ($employee['postal_code'] ? ', ' . $employee['postal_code'] : '') . ($employee['city'] ? ' ' . $employee['city'] : '')) ?></li>
        </ul>

        <?php if (!empty($employee['emergency_contact'])): ?>
        <h5 class="mt-4 mb-3"><i class="bi bi-telephone-outbound me-2 text-danger"></i>Contact d'Urgence</h5>
        <p class="small">Nom: <?= htmlspecialchars($employee['emergency_contact']) ?> <br>Téléphone: <?= htmlspecialchars($employee['emergency_phone']) ?></p>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <h5 class="mb-3"><i class="bi bi-briefcase me-2"></i>Informations Professionnelles</h5>
         <dl class="row small">
            <dt class="col-sm-5">Date d'embauche:</dt> <dd class="col-sm-7"><strong><?= formatDate($employee['hire_date']) ?></strong></dd>
            <dt class="col-sm-5">Type de contrat:</dt>
            <dd class="col-sm-7">
                <strong>
                <?php
                    $typesContratSettings = parse_json_field($typesContratSettingsRaw);
                    $displayContract = htmlspecialchars($employee['contract_type'] ?? 'Non spécifié');
                    if (!empty($typesContratSettings) && is_array($typesContratSettings) && in_array($employee['contract_type'], $typesContratSettings)) {
                        echo htmlspecialchars($employee['contract_type']);
                    } else {
                        $legacyContractTypes = ['cdi' => 'CDI', 'cdd' => 'CDD', 'stage' => 'Stage', 'interim' => 'Intérim', 'essai' => 'Essai'];
                        echo htmlspecialchars($legacyContractTypes[$employee['contract_type']] ?? $employee['contract_type'] ?? 'Non spécifié');
                    }
                ?>
                </strong>
            </dd>
            <dt class="col-sm-5">Date de fin de contrat:</dt>
            <dd class="col-sm-7">
                <?php if (!empty($employee['end_date'])): ?>
                    <?= formatDate($employee['end_date']) ?>
                    <?php
                    try {
                        $endDate = new DateTime($employee['end_date']); $today = new DateTime();
                        if ($endDate > $today) { $interval = $today->diff($endDate); echo ' <span class="badge bg-success ms-1"><i class="bi bi-clock"></i> J-' . $interval->days . '</span>'; }
                        else { echo ' <span class="badge bg-danger ms-1"><i class="bi bi-exclamation-triangle"></i> Expiré</span>'; }
                    } catch (Exception $e) { /* Ignore date error */ }
                    ?>
                <?php else: ?> N/A <?php endif; ?>
            </dd>
            <?php if ($employee['status'] === 'inactive' || $employee['status'] === 'cancelled'): ?>
                <dt class="col-sm-5 text-danger">Date de sortie:</dt> <dd class="col-sm-7 text-danger"><strong><?= formatDate($employee['departure_date']) ?></strong></dd>
                <dt class="col-sm-5 text-danger">Motif de sortie:</dt> <dd class="col-sm-7 text-danger"><strong><?= htmlspecialchars($employee['departure_reason']?? 'N/A') ?></strong></dd>
            <?php endif; ?>
        </dl>
        
        <h5 class="mt-4 mb-3"><i class="bi bi-cash-coin me-2"></i>Rémunération & Soldes</h5>
        <dl class="row small">
             <dt class="col-sm-5">Salaire Brut Mensuel:</dt><dd class="col-sm-7"><?= number_format($employee['salary'], 2, ',', ' ') ?> DZD</dd>
             <dt class="col-sm-5">Solde Congé Annuel (N):</dt><dd class="col-sm-7"><?= number_format($employee['annual_leave_balance'], 1) ?> jour(s)</dd>
             <?php if (isset($employee['remaining_leave_balance']) && $employee['remaining_leave_balance'] > 0): ?>
             <dt class="col-sm-5 text-muted">Reliquat Congé (N-1):</dt><dd class="col-sm-7 text-muted"><?= number_format($employee['remaining_leave_balance'], 1) ?> jour(s)</dd>
             <?php endif; ?>
             <?php if (isset($current_recup_balance) && $current_recup_balance > 0): ?>
             <dt class="col-sm-5">Solde Récupération Actuel:</dt><dd class="col-sm-7"><?= number_format($current_recup_balance, 0) ?> jour(s)</dd>
             <?php endif; ?>
        </dl>
         <?php if (!empty($employee['bank_name'])): ?>
        <h5 class="mt-4 mb-3"><i class="bi bi-bank me-2"></i>Coordonnées Bancaires</h5>
        <dl class="row small">
            <dt class="col-sm-5">Nom de la Banque:</dt><dd class="col-sm-7"><?= htmlspecialchars($employee['bank_name']?? 'N/A') ?></dd>
            <dt class="col-sm-5">RIB (20 chiffres):</dt><dd class="col-sm-7"><?= htmlspecialchars($employee['rib']?? 'N/A') ?></dd>
            <?php if (!empty($employee['bank_account'])): ?>
            <dt class="col-sm-5">Numéro de Compte:</dt><dd class="col-sm-7"><?= htmlspecialchars($employee['bank_account']?? 'N/A') ?></dd>
            <?php endif; ?>
        </dl>
         <?php endif; ?>
    </div>
</div>