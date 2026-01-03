<?php
/**
 * Advanced Filters Component for PTW System
 * Provides comprehensive filtering and search functionality
 */

// Get filter values from URL
$filterStatus = $_GET['status'] ?? '';
$filterFirma = $_GET['firma'] ?? '';
$filterJobansvarlig = $_GET['jobansvarlig'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterSearch = $_GET['search'] ?? '';
$filterApproval = $_GET['approval'] ?? '';
$savedFilterId = $_GET['saved_filter'] ?? '';

// Get unique values for dropdowns - cache in session for 5 minutes
$firmaOptions = [];
$jobansvarligOptions = [];
$savedFilters = [];

try {
    if (isset($db)) {
        $cacheExpiry = 300; // 5 minutes
        $now = time();

        // Check if cached filter options are still valid
        if (isset($_SESSION['filter_cache_time']) && ($now - $_SESSION['filter_cache_time']) < $cacheExpiry) {
            $firmaOptions = $_SESSION['cached_firma_options'] ?? [];
            $jobansvarligOptions = $_SESSION['cached_jobansvarlig_options'] ?? [];
        } else {
            // Refresh cache
            $firmaResults = $db->fetchAll("
                SELECT DISTINCT entreprenor_firma
                FROM work_orders
                WHERE entreprenor_firma IS NOT NULL AND entreprenor_firma != ''
                ORDER BY entreprenor_firma
            ");
            $firmaOptions = array_column($firmaResults, 'entreprenor_firma');

            $jobResults = $db->fetchAll("
                SELECT DISTINCT jobansvarlig
                FROM work_orders
                WHERE jobansvarlig IS NOT NULL AND jobansvarlig != ''
                ORDER BY jobansvarlig
            ");
            $jobansvarligOptions = array_column($jobResults, 'jobansvarlig');

            // Store in session cache
            $_SESSION['cached_firma_options'] = $firmaOptions;
            $_SESSION['cached_jobansvarlig_options'] = $jobansvarligOptions;
            $_SESSION['filter_cache_time'] = $now;
        }

        // Get saved filters for current user (always fresh, it's user-specific)
        if (isset($_SESSION['user_id'])) {
            $savedFilters = $db->fetchAll("
                SELECT id, name, filters
                FROM saved_filters
                WHERE user_id = ?
                ORDER BY name
            ", [$_SESSION['user_id']]);
        }
    }
} catch (Exception $e) {
    // Tables might not exist
}

// Check if any filters are active
$hasActiveFilters = !empty($filterStatus) || !empty($filterFirma) || !empty($filterJobansvarlig)
    || !empty($filterDateFrom) || !empty($filterDateTo) || !empty($filterSearch) || !empty($filterApproval);
?>

<!-- Advanced Filters Panel -->
<div class="advanced-filters-wrapper">
    <button class="filter-toggle-btn" onclick="toggleAdvancedFilters()" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        <span>Avanceret Filtrering</span>
        <?php if ($hasActiveFilters): ?>
            <span class="filter-active-badge">Aktiv</span>
        <?php endif; ?>
    </button>

    <div class="advanced-filters-panel" id="advancedFiltersPanel">
        <form method="GET" class="filters-form" id="filtersForm">
            <!-- Search Row -->
            <div class="filter-row search-row">
                <div class="filter-field full-width">
                    <label for="filterSearch">Fritekst soegning</label>
                    <div class="search-input-wrapper">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" id="filterSearch" name="search" placeholder="Soeg i PTW nr., beskrivelse, firma..."
                               value="<?php echo htmlspecialchars($filterSearch); ?>">
                    </div>
                </div>
            </div>

            <!-- Filter Row 1 -->
            <div class="filter-row">
                <div class="filter-field">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" name="status">
                        <option value="">Alle statusser</option>
                        <option value="planning" <?php echo $filterStatus === 'planning' ? 'selected' : ''; ?>>Planlagt</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Aktiv</option>
                        <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Afsluttet</option>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filterFirma">Entreprenoer</label>
                    <select id="filterFirma" name="firma">
                        <option value="">Alle firmaer</option>
                        <?php foreach ($firmaOptions as $firma): ?>
                            <option value="<?php echo htmlspecialchars($firma); ?>"
                                    <?php echo $filterFirma === $firma ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($firma); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filterJobansvarlig">Jobansvarlig</label>
                    <select id="filterJobansvarlig" name="jobansvarlig">
                        <option value="">Alle jobansvarlige</option>
                        <?php foreach ($jobansvarligOptions as $job): ?>
                            <option value="<?php echo htmlspecialchars($job); ?>"
                                    <?php echo $filterJobansvarlig === $job ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="filterApproval">Godkendelser</label>
                    <select id="filterApproval" name="approval">
                        <option value="">Alle</option>
                        <option value="pending" <?php echo $filterApproval === 'pending' ? 'selected' : ''; ?>>Afventer godkendelse</option>
                        <option value="approved" <?php echo $filterApproval === 'approved' ? 'selected' : ''; ?>>Fuldt godkendt i dag</option>
                        <option value="partial" <?php echo $filterApproval === 'partial' ? 'selected' : ''; ?>>Delvist godkendt</option>
                    </select>
                </div>
            </div>

            <!-- Filter Row 2 - Dates -->
            <div class="filter-row">
                <div class="filter-field">
                    <label for="filterDateFrom">Oprettet fra</label>
                    <input type="date" id="filterDateFrom" name="date_from"
                           value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>

                <div class="filter-field">
                    <label for="filterDateTo">Oprettet til</label>
                    <input type="date" id="filterDateTo" name="date_to"
                           value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>

                <?php if (!empty($savedFilters)): ?>
                <div class="filter-field">
                    <label for="savedFilter">Gemte filtre</label>
                    <select id="savedFilter" onchange="loadSavedFilter(this.value)">
                        <option value="">Vaelg gemt filter...</option>
                        <?php foreach ($savedFilters as $sf): ?>
                            <option value="<?php echo $sf['id']; ?>"
                                    data-filters="<?php echo htmlspecialchars($sf['filters']); ?>">
                                <?php echo htmlspecialchars($sf['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Active Filters Chips -->
            <?php if ($hasActiveFilters): ?>
            <div class="active-filters-chips" id="activeFiltersChips">
                <?php if ($filterStatus): ?>
                    <span class="filter-chip">
                        Status: <?php echo $filterStatus === 'planning' ? 'Planlagt' : ($filterStatus === 'active' ? 'Aktiv' : 'Afsluttet'); ?>
                        <button type="button" onclick="removeFilter('status')">x</button>
                    </span>
                <?php endif; ?>
                <?php if ($filterFirma): ?>
                    <span class="filter-chip">
                        Firma: <?php echo htmlspecialchars($filterFirma); ?>
                        <button type="button" onclick="removeFilter('firma')">x</button>
                    </span>
                <?php endif; ?>
                <?php if ($filterJobansvarlig): ?>
                    <span class="filter-chip">
                        Jobansvarlig: <?php echo htmlspecialchars($filterJobansvarlig); ?>
                        <button type="button" onclick="removeFilter('jobansvarlig')">x</button>
                    </span>
                <?php endif; ?>
                <?php if ($filterSearch): ?>
                    <span class="filter-chip">
                        Soegning: "<?php echo htmlspecialchars($filterSearch); ?>"
                        <button type="button" onclick="removeFilter('search')">x</button>
                    </span>
                <?php endif; ?>
                <?php if ($filterDateFrom || $filterDateTo): ?>
                    <span class="filter-chip">
                        Dato: <?php echo $filterDateFrom ?: '*'; ?> - <?php echo $filterDateTo ?: '*'; ?>
                        <button type="button" onclick="removeFilter('date_from'); removeFilter('date_to');">x</button>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    Anvend Filtre
                </button>

                <?php if ($hasActiveFilters): ?>
                    <a href="view_wo.php" class="btn btn-secondary">Nulstil</a>
                <?php endif; ?>

                <button type="button" class="btn btn-outline" onclick="showSaveFilterModal()">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
                    </svg>
                    Gem Filter
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Save Filter Modal -->
<div class="modal-overlay" id="saveFilterModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Gem Filter</h3>
            <button type="button" class="modal-close" onclick="closeSaveFilterModal()">x</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label for="filterName">Filternavn</label>
                <input type="text" id="filterName" placeholder="F.eks. 'Mine aktive PTW'er'" required>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeSaveFilterModal()">Annuller</button>
            <button type="button" class="btn btn-primary" onclick="saveCurrentFilter()">Gem</button>
        </div>
    </div>
</div>

<style>
/* Advanced Filters Styles */
.advanced-filters-wrapper {
    margin-bottom: 1.5rem;
}

.filter-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.625rem 1rem;
    background: var(--background-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    color: var(--text-primary);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.filter-toggle-btn:hover {
    border-color: var(--primary-color);
    box-shadow: var(--shadow-sm);
}

.filter-toggle-btn svg {
    color: var(--text-secondary);
}

.filter-active-badge {
    background: var(--primary-color);
    color: white;
    font-size: 0.7rem;
    padding: 0.15rem 0.5rem;
    border-radius: 10px;
    font-weight: 600;
}

.advanced-filters-panel {
    display: none;
    background: var(--background-primary);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-lg);
    padding: 1.25rem;
    margin-top: 0.75rem;
    box-shadow: var(--shadow-md);
}

.advanced-filters-panel.open {
    display: block;
    animation: slideDown 0.2s ease;
}

.filters-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}

.filter-row.search-row {
    grid-template-columns: 1fr;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
}

.filter-field.full-width {
    grid-column: 1 / -1;
}

.filter-field label {
    font-size: 0.85rem;
    font-weight: 500;
    color: var(--text-secondary);
}

.filter-field input,
.filter-field select {
    padding: 0.5rem 0.75rem;
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    background: var(--background-primary);
    color: var(--text-primary);
    transition: border-color 0.2s ease;
}

.filter-field input:focus,
.filter-field select:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper svg {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-light);
}

.search-input-wrapper input {
    padding-left: 2.5rem;
    width: 100%;
}

.active-filters-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--border-light);
}

.filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.5rem 0.25rem 0.75rem;
    background: rgba(30, 64, 175, 0.1);
    color: var(--primary-color);
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    font-weight: 500;
}

.filter-chip button {
    background: none;
    border: none;
    color: var(--primary-color);
    cursor: pointer;
    padding: 0 0.25rem;
    font-size: 1rem;
    line-height: 1;
    opacity: 0.7;
}

.filter-chip button:hover {
    opacity: 1;
}

.filter-actions {
    display: flex;
    gap: 0.75rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border-light);
    flex-wrap: wrap;
}

.filter-actions .btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 1rem;
    border-radius: var(--radius-md);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.filter-actions .btn-primary {
    background: var(--primary-color);
    color: white;
    border: none;
}

.filter-actions .btn-primary:hover {
    background: var(--primary-dark);
}

.filter-actions .btn-secondary {
    background: var(--background-secondary);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.filter-actions .btn-secondary:hover {
    background: var(--border-light);
}

.filter-actions .btn-outline {
    background: transparent;
    color: var(--primary-color);
    border: 1px solid var(--primary-color);
}

.filter-actions .btn-outline:hover {
    background: rgba(30, 64, 175, 0.05);
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10001;
    backdrop-filter: blur(4px);
}

.modal-content {
    background: var(--background-primary);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    width: 90%;
    max-width: 400px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--text-secondary);
    cursor: pointer;
}

.modal-body {
    padding: 1.25rem;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--border-light);
}

@media (max-width: 768px) {
    .filter-row {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        flex-direction: column;
    }

    .filter-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Advanced Filters JavaScript
function toggleAdvancedFilters() {
    const panel = document.getElementById('advancedFiltersPanel');
    const btn = document.querySelector('.filter-toggle-btn');

    panel.classList.toggle('open');
    btn.setAttribute('aria-expanded', panel.classList.contains('open'));
}

function removeFilter(name) {
    const input = document.querySelector(`[name="${name}"]`);
    if (input) {
        if (input.tagName === 'SELECT') {
            input.value = '';
        } else {
            input.value = '';
        }
    }
    document.getElementById('filtersForm').submit();
}

function showSaveFilterModal() {
    document.getElementById('saveFilterModal').style.display = 'flex';
}

function closeSaveFilterModal() {
    document.getElementById('saveFilterModal').style.display = 'none';
}

function saveCurrentFilter() {
    const name = document.getElementById('filterName').value.trim();
    if (!name) {
        alert('Indtast venligst et filternavn');
        return;
    }

    // Collect current filter values
    const form = document.getElementById('filtersForm');
    const formData = new FormData(form);
    const filters = {};

    formData.forEach((value, key) => {
        if (value) filters[key] = value;
    });

    // Save via API
    fetch('<?php echo $base ?? ''; ?>api/filters.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'save',
            name: name,
            filters: filters
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeSaveFilterModal();
            if (typeof showNotification === 'function') {
                showNotification('Filter gemt!', 'success');
            }
            // Reload to show new saved filter
            location.reload();
        } else {
            alert(data.message || 'Fejl ved gemning af filter');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Fejl ved gemning af filter');
    });
}

function loadSavedFilter(filterId) {
    if (!filterId) return;

    const select = document.getElementById('savedFilter');
    const option = select.querySelector(`option[value="${filterId}"]`);

    if (option) {
        try {
            const filters = JSON.parse(option.dataset.filters);
            const params = new URLSearchParams(filters);
            window.location.href = 'view_wo.php?' + params.toString();
        } catch (e) {
            console.error('Error loading filter:', e);
        }
    }
}

// Show panel if filters are active
<?php if ($hasActiveFilters): ?>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('advancedFiltersPanel').classList.add('open');
});
<?php endif; ?>
</script>
