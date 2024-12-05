<?php


<div class="process-flow-container">
  <div class="process-step <?php echo $data['progresscontent'][0] == 1 ? 'completed' : 'incomplete'; ?>">
    <div class="icon-container">
      <?php if ($data['progresscontent'][0] == 1) { ?>
        <i class="icon-bi bi-check-circle" style="color:green;"></i>
      <?php } else { ?>
        <i class="icon-bi bi-x-circle" style="color:red;"></i>
      <?php } ?>
    </div>
    <p>DEV</p>
  </div>
  <div class="process-step <?php echo $data['progresscontent'][1] == 1 ? 'completed' : 'incomplete'; ?>">
    <div class="icon-container">
      <?php if ($data['progresscontent'][1] == 1) { ?>
        <i class="icon-bi bi-check-circle" style="color:green;"></i>
      <?php } else { ?>
        <i class="icon-bi bi-x-circle" style="color:red;"></i>
      <?php } ?>
    </div>
    <p>TEST</p>
  </div>
  <div class="process-step <?php echo $data['progresscontent'][2] == 1 ? 'completed' : 'incomplete'; ?>">
    <div class="icon-container">
      <?php if ($data['progresscontent'][2] == 1) { ?>
        <i class="icon-bi bi-check-circle" style="color:green;"></i>
      <?php } else { ?>
        <i class="icon-bi bi-x-circle" style="color:red;"></i>
      <?php } ?>
    </div>
    <p>UAT</p>
  </div>
  <div class="process-step <?php echo $data['progresscontent'][3] == 1 ? 'completed' : 'incomplete'; ?>">
    <div class="icon-container">
      <?php if ($data['progresscontent'][3] == 1) { ?>
        <i class="icon-bi bi-check-circle" style="color:green;"></i>
      <?php } else { ?>
        <i class="icon-bi bi-x-circle" style="color:red;"></i>
      <?php } ?>
    </div>
    <p>PRELIVE</p>
  </div>
  <div class="process-step <?php echo $data['progresscontent'][4] == 1 ? 'completed' : 'incomplete'; ?>">
    <div class="icon-container">
      <?php if ($data['progresscontent'][4] == 1) { ?>
        <i class="icon-bi bi-check-circle" style="color:green;"></i>
      <?php } else { ?>
        <i class="icon-bi bi-x-circle" style="color:red;"></i>
      <?php } ?>
    </div>
    <p>LIVE</p>
  </div>
</div>

<!-- Cards for display a Author name, Files corresponding to the respective commit id in form format -->
<div class="container">
  <form id="toCommit" method="post">
    <?php if (in_array($data['roleId'], $data['permission'])): ?>
      <div class="form-check form-switch" id="buttons">
        <div class="toggle-switch">
          <label class="form-check-label" for="toggleCheck"><strong>Check for syntax</strong></label><br>
          <input type="checkbox" class="form-check-input ms-5" id="toggleCheck" checked>
        </div>
        <div id="btndiv">
          <input type="submit" class="btn btn-primary" id="cherryPickBtn"
            value="Cherry pick to <?php echo $data['progresscontent'][5] ?>">
        </div>
      </div>
    <?php endif; ?>
    <div class="container mt-5 d-flex flex-wrap">
      <?php foreach ($data['commit_details'] as $key => $value): ?>
        <div class="card p-2 me-1 mb-4 w-1" id="card_<?= $key ?>" onclick="select('<?= $key ?>')" style="">
          <div class="card-body">
            <div class="form-check input_check">
              <?php if (in_array($data['roleId'], $data['permission'])): ?>
                <input class="form-check-input main-checkbox" type="checkbox" name="commitIds[]" value="<?= $key ?>"
                  id="total_select_<?= $key ?>" data-card="card_<?= $key ?>">
              <?php endif; ?>
              <p class="d-none" id="commit_details"
                data-commit_details="<?php echo json_encode($data['commit_details']); ?>">
              <p class="d-none" id="from_branch" data-from_branch="<?= $data['from_branch'] ?>">
              <p class="d-none" id="branch" data-branch="<?= $data['progresscontent'][5] ?>">
              <p class="d-none" id="backlog_id" data-backlog_id="<?= $data['backlog_id'] ?>">
              <p class="d-none" id="sprint_id" data-sprint_id="<?= $data['sprint_id'] ?>">
              <p class="d-none" id="num_of_files" data-num_of_files="<?php echo $filesCount = count($value['files']) ?>">
            </div>
            <p id="msg"><i class="bi bi-file-earmark-text"></i> Msg :
              <b><?php echo strtoupper($value[0]['commit_message']); ?></b>
            </p>
            <p id="commitMsg"><i class="bi bi-person-circle" style="color:blue;"></i> By : <b
                id="details"><?= strtoupper($value[0]['author']); ?></b></p>
            <p id="commitMsg"><i class="bi bi-calendar" style="color:blue;"></i> On : <b
                id="details"><?= strtoupper($value[0]['date']) ?></b></p>
            <p>
            <div id="commitMsg" class="d-flex flex-wrap"><i class="bi bi-file-earmark" style="color:blue;">
              </i> Files : &nbsp;<b id="details"><?= count($value['files']) ?></b>
              <div class="commit-item ms-2" id="diff_btn" data-sha="<?php echo $key ?>"
                style="text-decoration:underline;"><i class="bi bi-eye"></i><b> DIFF</b></div>
            </div>
            </p>

            <p id="commitMsg"><i class="bi bi-list" style="color:blue;font-size:15px;"></i> Files list :
              <?php foreach ($value['files'] as $key2 => $value2): ?>
              <div class="file-item file_list" id="file_<?= $key ?>_<?= $key2 ?>">
                <span class="file_color"><?= $value2; ?></span>
              </div>
            <?php endforeach; ?>
            </p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<div class="modal fade" id="filedetails" tabindex="-1" aria-labelledby="filedetailsLabel" aria-hidden="true"
  data-bs-backdrop="static">
  <div class="modal-dialog modal-lg" style="max-width: 75%;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="filedetailsLabel">Commits:</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body form-container">
        <div id="myDiffElement"></div>
      </div>
    </div>
  </div>
</div>

<div id="cherryPickModal" class="modal fade" tabindex="-1" aria-labelledby="cherryPickModalLabel" aria-hidden="true"
  data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="cherryPickModalLabel">Are you sure?</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="confirmation-text">
          <p>Please check before Cherry Picking. Selected commits:</p>
          <ul id="selected-commits-list"></ul>
        </div>
        <div id="commit-details">
          <p>Loading commit details...</p>
        </div>
      </div>
      <div class="modal-footer">
        <div class="mergeBtn">
          <button id="view-diff-btn" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#diffModal">
            Merge Preview
          </button>
        </div>
        <div>
          <button id="cancel-btn" type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, cancel!</button>
          <button id="confirm-btn" type="button" class="btn btn-primary">Yes, cherry pick!</button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal for displaying syntax errors -->
<div id="syntaxErrorModal" class="modal fade" tabindex="-1" aria-labelledby="syntaxErrorModalLabel" aria-hidden="true"
  data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="syntaxErrorModalLabel">Syntax Error Report</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="error-summary">
          <p>Syntax errors found in the selected commit(s):</p>
          <ul id="error-commit-list"></ul>
        </div>
        <div id="error-details" style="max-height: 300px; overflow-y: auto;">
          <p>Select a file to see its details:</p>
          <div id="file-error-content"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
sdklf.xdm.gcx v,x,md vx vxv
<!-- Diff Modal -->
<div id="diffModal" class="modal fade" tabindex="-1" aria-labelledby="diffModalLabel" aria-hidden="true"
  data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title" id="diffModalLabel">After Merge Preview</h3>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="diff-viewer">Loading diff...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<div id="syntaxErrorModalSecondary" class="modal fade" tabindex="-1" aria-labelledby="syntaxErrorModalSecondaryLabel"
  aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="syntaxErrorModalSecondaryLabel">Syntax Errors</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <ul id="error-commit-list-secondary" class="list-unstyled">
          <!-- Commit and file list dynamically inserted here -->
        </ul>
        <div id="file-error-content-secondary" class="mt-4">
          <!-- Error details dynamically displayed here -->
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<script>
  const array = <?php echo json_encode($data['commit_details']); ?>;
</script>
?>