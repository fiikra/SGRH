<div class="table-responsive">
    <table class="table table-hover table-striped table-bordered table-sm">
        <thead class="table-light">
            <tr>
                <th>Date</th>
                <th>Employé</th>
                <th>Statut Pointage</th>
                <th>Notes/Type</th>
                <th>HS Mens.</th>
                <th>Retenue Mens.</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($attendanceRecords)): ?>
                <tr><td colspan="6" class="text-center">Aucun enregistrement trouvé pour les filtres sélectionnés.</td></tr>
            <?php else: foreach ($attendanceRecords as $record): ?>
                <?php
                    $note = trim($record['leave_type_if_absent'] ?? $record['notes'] ?? '');
                    $codeDetails = $attendanceCodeMap[$note] ?? ['label' => 'N/A', 'badge' => 'bg-dark'];
                    
                    $summary = $monthlySummaries[$record['employee_nin']] ?? null;
                    $hsDisplay = $summary ? number_format((float)$summary['total_hs_hours'], 2) . 'h' : '-';
                    $retenueDisplay = $summary ? number_format((float)$summary['total_retenu_hours'], 2) . 'h' : '-';
                ?>
                <tr>
                    <td><?= formatDate($record['attendance_date'] ?? null, 'd/m/Y') ?></td>
                    <td>
                        <a href="<?= route('employees_view', ['nin' => $record['employee_nin']]) ?>">
                            <?= htmlspecialchars($record['first_name'] . ' ' . $record['last_name']) ?>
                        </a>
                        <small class="text-muted d-block">(<?= htmlspecialchars($record['employee_nin']) ?>)</small>
                    </td>
                    <td><span class="badge <?= $codeDetails['badge'] ?>"><?= htmlspecialchars($codeDetails['label']) ?></span></td>
                    <td><small><?= htmlspecialchars($note) ?></small></td>
                    <td class="text-center"><?= $hsDisplay ?></td>
                    <td class="text-center"><?= $retenueDisplay ?></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<div class="mt-3">
    <h5>Légende des codes :</h5>
    <ul>
        <?php foreach ($attendanceCodeMap as $code => $details): ?>
            <li><span class="badge <?= $details['badge'] ?>"><?= htmlspecialchars($code) ?></span> : <?= htmlspecialchars($details['label']) ?></li>
        <?php endforeach; ?>
    </ul>
</div>