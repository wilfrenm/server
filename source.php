<script>
    // Define the base URL constant for API and resource requests
    const BASE_URL = "<?= ASSERT_PATH ?>";
    // Encode PHP data array into JSON for use in JavaScript
    let data = <?php echo json_encode($data); ?>;
</script>

<div class="cls-backlog-right-side-buttons d-flex justify-content-end">
    <ul class="header_button list-unstyled d-flex">
        <li class="me-2">
            <div class="dropdown">
                <div class="search-container">
                    <div class="search-box">
                        <input type="text" id="projectSearchInput" class="search-input" placeholder="Search Project">
                        <button id="search_btn" class="search-button">
                            <i class="icon-search"></i>
                            <span>Search</span>
                        </button>
                    </div>
                </div>
            </div>
        </li>
        <li>
            <div class="dropdown">
                <button id="filter_btn" class="button-secondary d-flex align-items-center justify-content-center">
                    <i class="icon-filter me-1"></i>
                    <span class="cls-action-name">Filter</span>
                    <span id="noti" class="badge badge-pill badge-danger"></span>
                </button>
            </div>
        </li>
    </ul>
</div>

<div id="filterSidebar" class="filter-sidebar">
    <div class="sidebar-content">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h4 class="filter-heading">Filter</h4>
            <button type="button" id="closeFilterSidebarBtn" class="btn-filterclose align-item-center"></button>
        </div>
        <form id="filterOptionsForm">
            <div class="mb-3">
                <label for="filterPriority" class="form-label filter-text">Product</label>
                <select name="product_id[]" id="filterPriority" placeholder="Select Product" class="form-select select"
                    multiple>
                    <?php foreach ($data as $val): ?>
                        <option value="<?= $val['product_id']; ?>"><?= trim(ucfirst(strtolower($val['Project']))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="git_url" class="form-label filter-text">Git URL</label>
                <input type="text" name="git_url" id="git_url" placeholder="Git URL" class="form-control" />
            </div>
            <div class="mb-3">
                <label for="jenkins_url" class="form-label filter-text">Jenkins URL</label>
                <input type="text" name="jenkins_url" id="jenkins_url" placeholder="Jenkins URL" class="form-control" />
            </div>
            <div class="mb-3">
                <label for="ip_address" class="form-label filter-text">IP Address</label>
                <input type="text" name="ip_address" id="ip_address" placeholder="IP Address" class="form-control" />
            </div>
            <div class="mb-3">
                <label for="server_url" class="form-label filter-text">Server URL</label>
                <input type="text" name="server_url" id="server_url" placeholder="Server URL" class="form-control" />
            </div>
            <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn primary_button">Apply</button>
                <button type="reset" class="btn button-secondary apply-reset-filters-btn"
                    id="resetFiltersBtn">Reset</button>
            </div>
        </form>
    </div>
</div>
<div class="main-page" id="mainPage">
    <div class="page">
        <div class="row">
            <div>
                <div class="row">
                    <table id="priority-table" class="table table-borderless custom-table cls-tabler">
                        <thead id="tableHeader" class="header_color">
                        </thead>
                        <tbody id="tableBody" class="body_color">
                        </tbody>
                    </table>
                    <div id="paginationControls" class="d-flex justify-content-between align-items-center mt-3">
                        <button id="prevPage" class="button btn-primary">Prev</button>
                        <span id="pageInfo" class="mx-3"></span>
                        <button id="nextPage" class="button btn-primary">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $sample = 'sources ?>