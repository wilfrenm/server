var serverURL = "http://localhost/Git-check/server/test.php";

$(document).ready(function () {
  // Declare global variables
  let productId,
    productName,
    sprintId,
    sprintName,
    backlogId,
    backlogName,
    branchLeft,
    branchRight,
    overallBranches;

  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((tooltip) => {
    new bootstrap.Tooltip(tooltip);
  });

  // Hide the commit details table initially
  $("#card-commit-detail").hide();
  $("#commitsview").hide();
  $("#filesview").hide();
  $("#track-history").hide();
  $("#file-tbl").hide();
  $("#clicked-drpdown-names").hide();
  $("#toggleDropdowns").hide();

  // Initialize the dropdowns
  initializeProducts();
  refineCode();
  getAllBranches();
});

/**
 * @purpose   Intializes the product dropdown with user assigned projects
 */
function initializeProducts() {
  const storedProductId = sessionStorage.getItem("productId");
  const storedSprintId = sessionStorage.getItem("sprintId");
  const storedBacklogId = sessionStorage.getItem("backlogId");

  // Load products first
  loadDropdown("#product", "product", null);

  if (storedProductId != null) {
    setTimeout(() => {
      productId = parseInt(storedProductId);
      $("#product").val(productId).trigger("change"); // Set product dropdown and trigger change
      loadDropdown("#sprint", "sprint", productId); // Load sprints
      if (storedSprintId) {
        sprintId = storedSprintId;
        setTimeout(() => {
          $("#sprint").val(sprintId).trigger("change");
          loadDropdown("#backlog", "backlog", sprintId); // Load backlogs
          if (storedBacklogId) {
            backlogId = storedBacklogId;
            setTimeout(
              () => $("#backlog").val(backlogId).trigger("change"),
              500
            );
          }
        }, 500); // Delay to ensure dropdown is ready
      }
    }, 500); // Delay to ensure products are loaded
  }
}

/**
 * @purpose   Loads data into a target dropdown based on type and parent ID
 * @param {string} targetDropdown
 * @param {string} type
 * @param {number|null} parentId
 */
function loadDropdown(targetDropdown, type, parentId = null) {
  if ($(targetDropdown).find("option").length > 1) {
    return;
  }

  $(targetDropdown)
    .empty()
    .append(`<option selected disabled>Please select a ${type}</option>`);

  if (type === "sprint") {
    $("#sprintContainer").show();
    $("#backlogContainer").hide(); // Hide backlog initially
  } else if (type === "backlog") {
    $("#backlogContainer").show(); // Show backlog once sprint is selected
  }

  let url = `${baseUrl}release-management/getData?type=${type}`;
  if (parentId) {
    url += `&parent_id=${parentId}`;
  }

  $.ajax({
    url: url,
    method: "GET",
    success: function (data) {
      let options = data
        .map(
          (item) => `
            <option value="${item.id}" data-name="${item.name}">
                ${item.name}
            </option>
        `
        )
        .join("");
      $(targetDropdown).append(
        data.length > 0
          ? options
          : `<option disabled>No ${type}s found</option>`
      );
    },
    error: function () {
      $(targetDropdown).append(`<option disabled>${type} Not Found!</option>`);
    },
  });
}

$("#product").change(function () {
  const selectedProductId = $(this).val();
  const selectedProductName = $(this).find("option:selected").data("name");

  productId = selectedProductId;
  productName = selectedProductName;

  loadDropdown("#sprint", "sprint", productId);
});

$("#sprint").change(function () {
  const selectedSprintId = $(this).val();
  const selectedSprintName = $(this).find("option:selected").data("name");

  sprintId = selectedSprintId;
  sprintName = selectedSprintName;

  loadDropdown("#backlog", "backlog", sprintId);
});

$("#backlog").change(function () {
  const selectedBacklogId = $(this).val();
  const selectedBacklogName = $(this).find("option:selected").data("name");

  backlogId = selectedBacklogId;
  backlogName = selectedBacklogName;

  // Call saveDropdownState only after backlog is selected
  saveDropdownState();

  showCommitDetails();
  appendDropdownValues();
});

/**
 * @purpose   Saves current state of dropdown selection
 */
function saveDropdownState() {
  // sessionStorage.clear();
  sessionStorage.setItem("productId", productId);
  sessionStorage.setItem("productName", productName);
  sessionStorage.setItem("sprintId", sprintId);
  sessionStorage.setItem("sprintName", sprintName);
  sessionStorage.setItem("backlogId", backlogId);
  sessionStorage.setItem("backlogName", backlogName);
  console.log("productID @ set", productId);
  console.log("sprintID @ set", sprintId);
  console.log("backlogID @ set", backlogId);
  forSession();
}

/**
 * @purpose   Retrieves and applies dropdown states from session storage
 */
function forSession() {
  $.ajax({
    url: `${baseUrl}/release-management/trackDetailSession`,
    type: "POST",
    data: {
      product_id: productId,
      product_name: productName,
      sprint_id: sprintId,
      sprint_name: sprintName,
      backlog_id: backlogId,
      backlog_name: backlogName,
    },
    success: function (response) {
      console.log("Session data stored successfully:", response);
    },
    error: function (xhr, status, error) {
      console.error("Error storing session data:", error);
    },
  });
}

/**
 * @purpose   Appends selected dropdown values to display related views
 */
function appendDropdownValues() {
  const selectedProduct = $("#product").find("option:selected").text();
  const selectedProductId = $("#product").find("option:selected").val();

  const selectedSprint = $("#sprint").find("option:selected").text();
  const selectedSprintId = $("#sprint").find("option:selected").val();

  const selectedBacklog = $("#backlog").find("option:selected").text();
  const selectedBacklogId = $("#backlog").find("option:selected").val();

  // Append selected values to respective divs
  $("#drpdown-product-value").html(
    `<h6>Project:<strong> ${selectedProduct}</strong> </h6>`
  );
  $("#drpdown-sprint-value").html(
    `<h6>Sprint:<strong> ${selectedSprint}</strong></h6>`
  );
  $("#drpdown-backlog-value").html(
    `<h6>Backlog:<strong> ${selectedBacklog}</strong> </h6>`
  );

  // Use slideUp and fadeIn with a delay to ensure smooth animation
  $("#dropdowns")
    .slideUp(800)
    .promise()
    .done(function () {
      $("#clicked-drpdown-names").fadeIn(800);
      $("#toggleDropdowns").fadeIn(800); // Ensure the toggle button also fades in
    });
}

/**
 * @purpose   Toggles the visibility of dropdown menus
 */
function toggleDropdowns() {
  const dropdownsVisible = $("#dropdowns").is(":visible");

  if (dropdownsVisible) {
    // Hide dropdowns with slide up, show selected values with fade-in
    $("#dropdowns").slideUp(800, function () {
      $("#clicked-drpdown-names").fadeIn(800);
      $("#toggleDropdowns").fadeIn(800); // Ensure the toggle button also fades in
    });
  } else {
    // Clear session storage
    sessionStorage.clear();
    console.log("Session storage cleared.");

    // Hide selected values with fade-out, show dropdowns with slide down
    $("#clicked-drpdown-names").fadeOut(800, function () {
      $("#dropdowns").slideDown(800);
    });

    // Optionally reset dropdowns to default state
    $("#product, #sprint, #backlog").val(""); // Clear selected values
    $("#sprint, #backlog").empty(); // Clear sprint and backlog options
    $("#sprintContainer, #backlogContainer").hide(); // Hide dependent dropdowns

    // Reload products dropdown
    loadDropdown("#product", "product", null);
  }
}

/**
 * @purpose   Displays commit details in a detailed view, based on response from server file
 */
function showCommitDetails() {
  popup(
    "Fetching Commit Data...",
    "Connecting to Jenkins.. Please wait...",
    null,
    null,
    true,
    '<i class="fas fa-cogs" style="font-size: 2rem; color: cornflowerblue;;"></i>'
  );
  if (sprintId && backlogId) {
    $.ajax({
      url: `${baseUrl}release-management/jenkinRequest/${encodeURIComponent(
        sprintId
      )}/${encodeURIComponent(backlogId)}`,
      success: function (response) {
        if (response.status === "success") {
          console.log("Jenkins request successful:", response);
          generateCommitCards(response.data);
          Swal.close();
        } else {
          popup(
            "No Response Found",
            "Invalid response status.",
            "error",
            "OK",
            false
          );
          console.error("Error: Invalid response status");
        }
      },
      error: function (xhr, status, error) {
        popup("Something wrong !", error, "error", "OK", false);
        console.error("Jenkins request failed:", error);
      },
    });
  } else {
    Swal.close();
    setTimeout(() => {
      popup(
        "Commits Not Found!",
        "Please select valid sprint and backlog",
        "error",
        "OK",
        false
      );
    }, 100);
  }

  // Show/hide tables based on the selected view
  if ($("#commit-view").is(":checked")) {
    $("#card-commit-detail").show(); // //tblCommitDetails
    $("#file-tbl").hide();
  }

  $("#commitsview").show();
  $("#filesview").show();
  $("#track-history").show();
}

/**
 * @purpose   Generate cards based on response
 * @param {object} data
 */
function generateCommitCards(data) {
  console.log(data);
  const environments = [
    {
      name: "Development Environment",
      key: "dev", //overallBranches[0]
      icon: "bi bi-code-slash",
      color: "text-primary",
    },
    {
      name: "Test Environment",
      key: "test", //overallBranches[1]
      icon: "bi bi-cloud-upload",
      color: "text-warning",
    },
    {
      name: "UAT Environment",
      key: "uat", //overallBranches[2]
      icon: "bi bi-file-earmark-check",
      color: "text-info",
    },
    {
      name: "Prelive Environment",
      key: "prelive", //overallBranches[3]
      icon: "bi bi-gear",
      color: "text-secondary",
    },
    {
      name: "Live Environment",
      key: "live", //overallBranches[4]
      icon: "bi bi-check-lg",
      color: "text-success",
    },
  ];

  const totals = data[1]; // Total commits for each environment
  const container = document.querySelector(
    "#card-commit-detail .card-container"
  );
  container.innerHTML = ""; // Clear existing content

  environments.forEach((env, index) => {
    const currentEnvTotal = totals[env.key] || 0;
    let trackedCommits = 0;
    let missedCommits = 0;

    let serializedData = "[]"; // Default to empty array
    if (data[0][env.key] && Array.isArray(data[0][env.key])) {
      serializedData = encodeURIComponent(JSON.stringify(data[0][env.key]));
    } else if (data[0][env.key]) {
      serializedData = encodeURIComponent(JSON.stringify([data[0][env.key]]));
    }

    if (index === 0) {
      trackedCommits = 0;
      missedCommits = currentEnvTotal;
    } else {
      const previousEnvTotal = totals[environments[index - 1].key] || 0;
      trackedCommits = currentEnvTotal;
      missedCommits = previousEnvTotal - currentEnvTotal;
    }

    const previousEnvName =
      index > 0 ? environments[index - 1].name : "Previous Environment";

    const footerHTML =
      index === 0
        ? `<div class="card-footer"></div>`
        : ` 
        <div class="card-footer">
          <a href="#" class="card-link text-success merged" data-bs-toggle="tooltip" title="Commits Merged from ${previousEnvName}">
            <i class="bi bi-check-circle"></i> Merged: ${trackedCommits} commits
          </a><br>
          <a href="#" class="card-link ${
            missedCommits > 0 ? "text-danger" : "text-muted merged"
          }" data-bs-toggle="tooltip" title="Commits not Merged from ${previousEnvName}" onclick="loadCommitModal(event,'${serializedData}')">
            <i class="bi bi-x-circle"></i> Not Merged: <span class="not-merge">${missedCommits} commits</span>
          </a><br><i class="bi bi-arrow-right-circle-fill btn-arrow" onclick="loadCommitModal(event,'${serializedData}')"></i>
        </div>`;

    const cardHTML = `
      <div class="card-wrapper">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <i class="${env.icon} fs-2 ${env.color}"></i>
            <h5 class="card-title mt-2">${env.name}</h5>
            <h4 class="card-title text-success">${currentEnvTotal} Commits</h4>
          </div>
          ${footerHTML}
        </div>
      </div>`;

    container.innerHTML += cardHTML;
  });

  // Scroll to the card container after loading
  setTimeout(() => {
    const cardSection = document.querySelector("#card-commit-detail");
    if (cardSection) {
      cardSection.scrollIntoView({
        behavior: "smooth", // Smooth scrolling effect
        block: "start", // Align to the start of the element
      });
    }
  }, 300); // Delay of 300ms to ensure rendering is complete
}

/**
 * @purpose   Loads commit details
 * @param {Event} event
 * @param {string} commitGroup
 */
function loadCommitModal(event, commitGroup) {
  event.preventDefault(); // Prevent the default anchor click behavior

  // Decode the passed string back to the original JSON format
  const groupData = JSON.parse(decodeURIComponent(commitGroup));

  // Check if no commits are present
  if (groupData.length === 0) {
    const commitDetailModal = $("#detail-modal");
    commitDetailModal.find(".modal-footer").remove();
    // Show a message when there are no commits in the group
    const commitDetailsContent = $("#commitDetailsContent");
    commitDetailsContent.empty(); // Clear any previous content
    commitDetailsContent.append(`
    <div class="alert alert-light">No commits to show for this environment. All commits are already merged.</div>
  `);
    $("#commitDetailsModal").modal("show");
    return;
  }

  const commitDetailsContent = $("#commitDetailsContent");
  commitDetailsContent.empty(); // Clear any previous content

  // Function to format the date
  function formatDate(dateString) {
    const date = new Date(dateString);
    const options = { day: "2-digit", month: "short", year: "numeric" };
    const formattedDate = date.toLocaleDateString("en-GB", options);

    // Add the comma after the day
    return formattedDate.replace(/(\d{2}) (\w{3}) (\d{4})/, "$1, $2 $3");
  }

  // Iterate over the grouped data and create a card for each commit
  groupData.forEach((commit, index) => {
    const formattedDate = formatDate(commit.started_datetime);

    // Append the commit details and dynamic button
    commitDetailsContent.append(`
      <div class="card mb-3">
          <div class="card-header c-head">
              <div class="title-product">
                  <h4 class="product-title"><strong>Author:</strong> ${commit.started_by}</h4>
              </div>
          </div>
          <div class="card-body-container">
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>Commit ID:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${commit.commit_id}</p>
                  </div>
              </div>
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>Date:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${formattedDate}</p>
                  </div>
              </div>
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>Message:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${commit.commit_message}</p>
                  </div>
              </div>
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>Repository Name:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${commit.repository_name}</p>
                  </div>
              </div>
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>Branch Name:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${commit.branch_name}</p>
                  </div>
              </div>
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>File Name:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${commit.file_name}</p>
                  </div>
              </div>
              <div class="row d-flex justify-content-center">
                  <div class="col-sm-5 text-left">
                      <p class="product-detail-label"><strong>File Status:</strong></p>
                  </div>
                  <div class="col-sm-6 text-left">
                      <p class="product-detail">${commit.file_status}</p>
                  </div>
              </div>
          </div>
      </div>
    `);
  });

  // Only append the "Proceed to Merge" button if there is commit data
  if (groupData.length > 0) {
    const commitDetailModal = $("#detail-modal");
    commitDetailModal.find(".modal-footer").remove(); // Remove existing footer to prevent duplication

    // Define a variable for the "commitgroup" link based on the last commit's branch
    let lastCommitGroupLink = "";

    switch (groupData[groupData.length - 1].branch_name) {
      case "dev":
        lastCommitGroupLink = "commitgroup/1";
        break;
      case "test":
        lastCommitGroupLink = "commitgroup/2";
        break;
      case "uat":
        lastCommitGroupLink = "commitgroup/3";
        break;
      case "prelive":
        lastCommitGroupLink = "commitgroup/4";
        break;
      default:
        lastCommitGroupLink = "commitgroup/1"; // Default to 'dev' if the branch name doesn't match
    }

    // commitDetailModal = $("#detail-modal");
    // // Clear any previous modal footer content
    // commitDetailModal.find(".modal-footer").remove();
    // Append the button outside the loop, at the end
    commitDetailModal.append(`
  <div class="modal-footer">
      <a class="btn primary_button " href="${lastCommitGroupLink}">Proceed to Merge</a>
  </div>
`);
  }
  // Show the modal
  $("#commitDetailsModal").modal("show");
}

/**
 * @purpose   toggles commit view and file view
 */
function refineCode() {
  // Set event listeners for branch dropdown changes
  $("#branchSelectLeft").change(function () {
    branchLeft = $(this).val();
    if (branchRight) loadFileViewData(); // Only call if both branches are selected
  });

  $("#branchSelectRight").change(function () {
    branchRight = $(this).val();
    if (branchLeft) loadFileViewData(); // Only call if both branches are selected
  });

  // Toggle views based on selected radio button
  $('input[name="view-toggle"]').change(function () {
    if ($("#commit-view").is(":checked")) {
      $("#card-commit-detail").show();
      $("#file-tbl").hide();
    } else if ($("#file-view").is(":checked")) {
      getAllBranches();
      $("#card-commit-detail").hide();
      $("#file-tbl").show();

      if (branchLeft && branchRight) {
        loadFileViewData();
      } else {
        popup(
          "No Files Found",
          "No files available in one of the branches.",
          "error",
          "OK",
          false
        );
        console.warn("Select both branches to view file data.");
      }
    }
  });
}

let lastBranchLeft = branchLeft;
let lastBranchRight = branchRight;

/**
 * @purpose   Retrieves all branches and populate branch dropdowns
 */
function getAllBranches() {
  $.ajax({
    url: `${baseUrl}release-management/fileGetBranches`,
    type: "GET",
    success: function (data) {
      overallBranches = data;
      branchLeft = data[0];
      branchRight = data[1];
      const leftSelect = $("#branchSelectLeft");
      const rightSelect = $("#branchSelectRight");

      const currentLeftValue = leftSelect.val(); // Store the current value
      const currentRightValue = rightSelect.val(); // Store the current value

      leftSelect.empty(); // Clear previous options
      rightSelect.empty();

      // Populate the dropdowns
      data.forEach((branch) => {
        const option = `<option value="${branch}">${branch}</option>`;
        leftSelect.append(option);
        rightSelect.append(option);
      });

      // Set default values only if nothing is selected
      if (!currentLeftValue && data.length > 0) {
        leftSelect.val(data[0]); // First branch selected
      } else {
        leftSelect.val(currentLeftValue); // Retain the user-selected value
      }

      if (!currentRightValue && data.length > 1) {
        rightSelect.val(data[1]); // Second branch selected
      } else {
        rightSelect.val(currentRightValue); // Retain the user-selected value
      }
    },
    error: function (xhr, status, error) {
      console.error("Error fetching branches:", error);
    },
  });
}

/**
 * @abstract    validates user authority for branch operations
 * @param {string} toEnvironment
 */
function branchAuthority(toEnvironment) {
  return new Promise((resolve, reject) => {
    $.ajax({
      url: `${baseUrl}/release-management/branchAuthority/` + toEnvironment,
      method: "GET",
      success: function (response) {
        if (
          response.roleId &&
          response.permissions &&
          Array.isArray(response.permissions)
        ) {
          const roleId = parseInt(response.roleId, 10);

          if (response.permissions.includes(roleId)) {
            console.log("Access granted");
            resolve(true); // Resolve with true when authorized
          } else {
            console.log("Access denied");
            resolve(false); // Resolve with false when denied
          }
        } else {
          console.error("Invalid response format");
          reject("Invalid response format"); // Reject if the format is invalid
        }
      },
      error: function () {
        reject("AJAX request failed");
      },
    });
  });
}

/**
 * @abstract    loads data for file views
 */
function loadFileViewData() {
  if (sprintId && backlogId) {
    popup(
      `Loading Files for ${backlogName}...`,
      `Preparing the File view for ${branchLeft} & ${branchRight}.. Please wait......`,
      null,
      null,
      true,
      '<i class="fas fa-folder-open folder" style="font-size: 2rem; color: #ffcc00;"></i>'
    );
    $.ajax({
      url: `${baseUrl}release-management/fileViewRequest/${encodeURIComponent(
        branchLeft
      )}/${encodeURIComponent(branchRight)}`,
      method: "GET",
      success: function (response) {
        Swal.close();
        if (response.status === "success") {
          console.log("File View request successful:", response);

          let data = JSON.parse(response.data);

          // Check if data_1 or data_2 is empty
          if (
            Array.isArray(data.data_1) &&
            data.data_1.length === 0 &&
            Array.isArray(data.data_2) &&
            data.data_2.length === 0
          ) {
            popup("No Files Found", error, "error", "OK", false);
            // Revert branches to previous values
            revertBranches();
          } else {
            populateFileTree(data); // Call your file tree population function
          }
        } else {
          popup(
            "Error",
            "Invalid response status for File View.",
            "error",
            "OK",
            false
          );
          // Revert branches to previous values
          revertBranches();
        }
      },
      error: function (xhr, status, error) {
        popup(
          "Request Failed",
          "An error occurred while processing the request.",
          "error",
          "OK",
          false
        );
        // Revert branches to previous values
        revertBranches();
        console.error("File View request failed:", error);
      },
    });
  } else {
    console.warn("Please select both a sprint and backlog to view files.");
    popup(
      "Please select both a sprint and backlog",
      "to view files.",
      "error",
      "OK",
      false
    );
  }
}

/**
 * @abstract    populates file tree structure with fetched data
 * @param {Object} data
 */
function populateFileTree(data) {
  // Clear any existing content before generating new file tree
  $("#fileTreeContentLeft").empty();
  $("#fileTreeContentRight").empty();

  // Generate file trees for each branch data, passing the appropriate date key
  generateFileTree(data.data_1, "fileTreeContentLeft", "date_branch1");
  generateFileTree(data.data_2, "fileTreeContentRight", "date_branch2");

  // Call this after populating the file trees
  syncFileTreeScroll($("#fileTreeLeft"), $("#fileTreeRight"));
  copyFiles();
}

/**
 * @abstract    generates a nested file tree structure
 * @param {Object} filesData
 * @param {string} paneId
 * @param {string} dateKey
 */
function generateFileTree(filesData, paneId, dateKey) {
  const fileTreePane = $("#" + paneId);

  const treeStructure = buildFileTree(filesData, dateKey);

  // Render the tree as HTML
  fileTreePane.append(renderTreeHtml(treeStructure));
}

/**
 * @abstract    Builds a hierarchical file tree
 * @param {Array} files
 * @param {string} dateKey
 */
function buildFileTree(files, dateKey) {
  const tree = {};

  files.forEach((fileData) => {
    const pathParts = fileData.file.split("/");
    let currentLevel = tree;

    // Traverse through each directory and file in the path
    pathParts.forEach((part, index) => {
      if (!currentLevel[part]) {
        currentLevel[part] = {};
      }

      // At the leaf node, store file metadata (status, date based on dateKey)
      if (index === pathParts.length - 1) {
        currentLevel[part] = {
          status: fileData.status,
          date: fileData[dateKey], // Dynamically use the date key
        };
      } else {
        currentLevel = currentLevel[part];
      }
    });
  });

  return tree;
}

/**
 * @abstract    Converts the file tree structure into HTML for rendering
 * @param {Object} tree
 */
function renderTreeHtml(tree) {
  let html = "<ul>";

  for (const name in tree) {
    const subTree = tree[name];
    const isFile = typeof subTree.status !== "undefined"; // Check if it’s a file (leaf node)

    if (isFile) {
      // Leaf node (file)
      const status = subTree.status || "";
      const date = subTree.date || "";

      html += `<li>
       <input class="form-check-input main-checkbox" type="checkbox" id="${name}" name="selectedFiles" value="${name}">
                      <a href='#' class='file-link' data-file='${name}' onclick='showContent(event, "${name}")'>
                        ${name}
                      </a>
                        <span class="file-date" style="font-weight: lighter; color: gray;"> Modified on: ${date}</span>
                        <span class="file-status" style="font-weight: bold;">
  ${getStatusIcon(status)}
</span>
                    </li>`;
    } else {
      // Branch node (directory)
      html += `<li><i class="bi bi-folder-fill" style="color:darkorange"></i> ${name}${renderTreeHtml(
        subTree
      )}</li>`;
    }
  }

  html += "</ul>";
  return html;
}

/**
 * @abstract    appropriate icon return
 * @param {String} status
 * @returns
 */
function getStatusIcon(status) {
  const tooltipTriggerList = document.querySelectorAll(
    '[data-bs-toggle="tooltip"]'
  );
  tooltipTriggerList.forEach((tooltipTriggerEl) => {
    new bootstrap.Tooltip(tooltipTriggerEl);
  });

  switch (status) {
    case "A": // Added in branchRight but not in branchLeft
      return `<i class="bi bi-plus-circle-fill" style="color: blue;" data-bs-toggle="tooltip" title="Branch not in '${branchLeft}'"></i>`;
    case "M": // Modified: branchLeft and branchRight are different
      return `<i class="bi bi-pencil-fill" style="color: orange;" data-bs-toggle="tooltip" title="Changes between '${branchLeft}' and '${branchRight}'"></i>`;
    case "D": // Deleted: not in branchRight
      return `<i class="bi bi-x-circle-fill" style="color: red;" data-bs-toggle="tooltip" title="Branch not in '${branchRight}'"></i>`;
    case "U": // Unchanged: branchLeft and branchRight are the same
      return `<i class="bi bi-check-circle-fill" style="color: green;" data-bs-toggle="tooltip" title="'${branchLeft}' and '${branchRight}' are identical"></i>`;
    default: // Unknown status
      return `<i class="bi bi-question-circle-fill" style="color: black;" data-bs-toggle="tooltip" title="Unknown status"></i>`;
  }
}

/**
 * @abstract    Synchronizes scrolling between two file tree panes
 * @param {HTMLElement} leftTree
 * @param {HTMLElement} rightTree
 */
function syncFileTreeScroll(leftTree, rightTree) {
  leftTree.on("scroll", function () {
    rightTree.scrollTop(leftTree.scrollTop());
  });

  rightTree.on("scroll", function () {
    leftTree.scrollTop(rightTree.scrollTop());
  });
}

/**
 * @abstract    multiple copies
 */
function copyFiles() {
  $("#copy-files").on("click", function () {
    branchAuthority(branchRight).then((isAuthorized) => {
      if (isAuthorized) {
        Swal.fire({
          title: "Are you sure?",
          text: "You want to Copy multiple files to " + branchRight + " Branch",
          icon: "warning",
          showCancelButton: true,
          confirmButtonText: "Yes, Copy!",
          cancelButtonText: "No, cancel!",
          reverseButtons: true,
        }).then((result) => {
          if (result.isConfirmed) {
            Swal.fire({
              title: `Enter Commit Message for files on ${branchRight}`,
              input: "text",
              inputLabel: "Commit Message",
              inputPlaceholder: "Enter your commit message here...",
              inputValidator: (value) => {
                if (!value) {
                  return "You need to write something!";
                }
              },
              iconHtml:
                '<i class="bi-chat-right-text" style="font-size: 2rem; color: grey;"></i>',
              showCancelButton: true,
              confirmButtonText: "Submit",
            }).then((commitResult) => {
              if (commitResult.isConfirmed && commitResult.value.trim()) {
                const commitMessage = commitResult.value.trim();

                popup(
                  `Copying files to ${branchRight}`,
                  `Copying your files.. Please wait...`,
                  null,
                  null,
                  true,
                  `
                   <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="green" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                     <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                     <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                     <path d="M16 11l2 2-2 2"></path>
                      <path d="M12 13h6"></path>
                    </svg>
                 `
                );
                // Collect checked files
                const checkedFiles = [];
                document
                  .querySelectorAll("input[name='selectedFiles']:checked")
                  .forEach((checkbox) => {
                    checkedFiles.push(checkbox.value);
                  });

                if (checkedFiles.length > 0) {
                  const postData = {
                    branchLeft: branchLeft,
                    branchRight: branchRight,
                    checkedFiles: checkedFiles,
                    commitMessage: commitMessage,
                  };
                  fetch(serverURL, {
                    method: "POST",
                    headers: {
                      "Content-Type": "application/json",
                      Action: "multipleCopy",
                    },
                    body: JSON.stringify(postData),
                  })
                    .then((response) => response.json())
                    .then((data) => {
                      Swal.close();
                      if (data.status === "success") {
                        popup(
                          "Succefully Copied!",
                          `Commit ID : ${data.commit_ids}`,
                          "success",
                          "OK",
                          false
                        ).then((result) => {
                          if (result.isConfirmed) {
                            numFiles = checkedFiles.length;
                            fileHistoryTracking(
                              data.from_commit_ids,
                              data.commit_ids,
                              backlogId,
                              sprintId,
                              branchRight,
                              numFiles,
                              branchLeft
                            );
                            loadFileViewData();
                          }
                        });
                      } else {
                        popup(
                          "Failed to Copy!",
                          `Error message : ${data.messages}`,
                          "error",
                          "OK",
                          false
                        );
                      }
                    })
                    .catch((err) => {
                      console.error("Error sending file name:", err);
                    });
                }
              } else {
                popup(
                  "Commit Message Required",
                  "Please provide a commit message.",
                  "warning",
                  "OK",
                  false
                );
              }
            });
          }
        });
      } else {
        popup(
          "Not authorize to copy..!",
          "Need to set authority on Environment setup page",
          "error",
          "OK",
          false
        );
      }
    });
  });
}

// Function to revert branches to last clicked values
/**
 * @abstract    Reverts changes in branches based on a rollback strategy
 */
function revertBranches() {
  branchLeft = lastBranchLeft;
  branchRight = lastBranchRight;

  $("#branchSelectLeft").val(branchLeft); // Left branch dropdown
  $("#branchSelectRight").val(branchRight); // Right branch dropdown
}

/**
 * @abstract    Displays content of a specific file when clicked
 * @param {Event} event
 * @param {string} fileName
 */
function showContent(event, fileName) {
  event.preventDefault(); // Prevent default anchor action

  // Update modal title with the file name
  document.getElementById("modalFileName").textContent = fileName;

  // Fetch file content
  fetchFileContent(fileName);

  // Show the modal
  const fileDiffModal = new bootstrap.Modal(
    document.getElementById("fileDiffModal")
  );
  fileDiffModal.show();
}

/**
 * @abstract    Fetches the content of a file from the server
 * @param {string} fileName
 */
function fetchFileContent(fileName) {
  popup(
    `Fetching ${fileName}..`,
    "Please wait...",
    null,
    null,
    true,
    '<i class="bi-body-text" style="font-size: 2rem; color: blueviolet;"></i>'
  );
  const postData = {
    file: fileName,
    branchLeft: branchLeft,
    branchRight: branchRight,
  };

  fetch(serverURL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Action: "particularFile",
    },
    body: JSON.stringify(postData),
  })
    .then((response) => response.json())
    .then((diffData) => {
      Swal.close();
      const leftPaneElement = document.getElementById("modalLeftPane");
      const rightPaneElement = document.getElementById("modalRightPane");

      // Clear previous editors
      leftPaneElement.innerHTML = "";
      rightPaneElement.innerHTML = "";

      leftPaneElement.innerHTML = `<div class="branchName"><strong>${branchLeft} branch:</strong> ${fileName}</div>`;
      rightPaneElement.innerHTML = `<div class="branchName"><strong>${branchRight} branch:</strong> ${fileName}</div>`;

      if (diffData.status === "success") {
        if (diffData.message === "Differences") {
          // const leftFileContent = diffData.content[0];
          // const rightFileContent = diffData.content[1];
          codeShow(
            diffData.content[0],
            diffData.content[1],
            leftPaneElement,
            rightPaneElement,
            fileName,
            diffData.changeType
          );
        } else if (diffData.message === "Unchanged") {
          codeShow(
            diffData.content[0],
            diffData.content[1],
            leftPaneElement,
            rightPaneElement,
            fileName,
            diffData.changeType
          );
        } else if (diffData.message === "Deleted") {
          codeShow(
            diffData.content[0],
            diffData.content[1],
            leftPaneElement,
            rightPaneElement,
            fileName,
            diffData.changeType
          );
        } else {
          // Fallback for no differences
          leftPaneElement.textContent =
            diffData.message || "No content available.";
          rightPaneElement.textContent = "";
        }
      } else {
        popup(
          "Something Wrong",
          `Error : diffData.message`,
          "error",
          "OK",
          false
        );
      }
    })
    .catch((err) => {
      console.error("Error fetching file content:", err);
    });
}

/**
 * @abstract    side-by-side comparison of file contents
 * @param {string} leftFileContent
 * @param {string} rightFileContent
 * @param {HTMLElement} leftPaneElement
 * @param {HTMLElement} rightPaneElement
 * @param {string} fileName
 * @param {string} changeType
 */
function codeShow(
  leftFileContent,
  rightFileContent,
  leftPaneElement,
  rightPaneElement,
  fileName,
  changeType
) {
  // const leftFileContent = diffData.content[0];
  // const rightFileContent = diffData.content[1];
  const dmp = new diff_match_patch();
  const diff = dmp.diff_main(leftFileContent, rightFileContent);
  dmp.diff_cleanupSemantic(diff);

  const leftEditor = CodeMirror(leftPaneElement, {
    value: leftFileContent,
    lineNumbers: true,
    mode: "javascript",
    theme: "eclipse",
    readOnly: true,
  });

  const rightEditor = CodeMirror(rightPaneElement, {
    value: rightFileContent,
    lineNumbers: true,
    mode: "javascript",
    theme: "eclipse",
    readOnly: false,
  });

  // Synchronize scrolling
  syncEditorScroll(leftEditor, rightEditor);

  highlightDiffs(diff, leftEditor, rightEditor);

  enableSaveButton(fileName, rightEditor, branchRight, changeType);
}

function highlightDiffs(diff, leftEditor, rightEditor) {
  let leftIndex = 0;
  let rightIndex = 0;

  diff.forEach(([type, text]) => {
    const numLines = (text.match(/\n/g) || []).length + 1;

    if (type === -1) {
      // Deletion: Highlight in the left editor
      leftEditor.markText(
        { line: leftIndex, ch: 0 },
        { line: leftIndex + numLines - 1, ch: text.length },
        { className: "diff-deletion" }
      );
      leftIndex += numLines;
    } else if (type === 1) {
      // Addition: Highlight in the right editor
      rightEditor.markText(
        { line: rightIndex, ch: 0 },
        { line: rightIndex + numLines - 1, ch: text.length },
        { className: "diff-addition" }
      );
      rightIndex += numLines;
    } else {
      // Unchanged: Just move both indexes forward
      leftIndex += numLines;
      rightIndex += numLines;
    }
  });
}

/**
 * @abstract    Synchronizes scrolling between two CodeMirror editors
 */
function syncEditorScroll(leftEditor, rightEditor) {
  leftEditor.on("scroll", function () {
    const leftScroll = leftEditor.getScrollInfo();
    rightEditor.scrollTo(leftScroll.left, leftScroll.top);
  });

  rightEditor.on("scroll", function () {
    const rightScroll = rightEditor.getScrollInfo();
    leftEditor.scrollTo(rightScroll.left, rightScroll.top);
  });
}

/**
 * @abstract    Enables the save button for file modifications and sets according to authority
 */
function enableSaveButton(fileName, editor, branch, changeType) {
  const saveChangesButton = document.getElementById("saveChangesButton");
  saveChangesButton.classList.remove("d-none");
  saveChangesButton.onclick = () =>
    saveChanges(fileName, editor.getValue(), branch, changeType);
}

/**
 * @abstract    Saves modified file content and commits it to a specified branch
 */
function saveChanges(fileName, editedContent, branchRight, changeType) {
  branchAuthority(branchRight).then((isAuthorized) => {
    if (isAuthorized) {
      Swal.fire({
        title: "Are you sure?",
        text: `Want to update the changes on ${branchRight} branch`,
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Yes, Update!",
        cancelButtonText: "No, cancel!",
        reverseButtons: true,
      }).then((result) => {
        if (result.isConfirmed) {
          const fileDiffModal = document.getElementById("fileDiffModal");
          const bootstrapModal = bootstrap.Modal.getInstance(fileDiffModal);
          bootstrapModal.hide();
          Swal.fire({
            title: `Enter Commit Message for ${fileName} on ${branchRight}`,
            input: "text",
            inputLabel: "Commit Message",
            inputPlaceholder: "Enter your commit message here...",
            inputValidator: (value) => {
              if (!value) {
                return "You need to write something!";
              }
            },
            iconHtml:
              '<i class="bi-chat-right-text" style="font-size: 2rem; color: grey;"></i>',
            showCancelButton: true,
            confirmButtonText: "Submit",
          }).then((commitResult) => {
            if (commitResult.isConfirmed && commitResult.value.trim()) {
              const commitMessage = commitResult.value.trim();

              popup(
                `Updating your ${fileName} on ${branchRight}`,
                "Please wait...",
                null,
                null,
                true,
                '<i class="fas fa-sync-alt" style="font-size: 2rem; color: #17a2b8;"></i>'
              );

              fetch(serverURL, {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                  Action: "responseCopy",
                },
                body: JSON.stringify({
                  file: fileName,
                  branchRight: branchRight,
                  editedContent: editedContent,
                  branchLeft: branchLeft,
                  changeType: changeType,
                  commitMessage: commitMessage,
                }),
              })
                .then((response) => {
                  return response.json();
                })
                .then((data) => {
                  Swal.close();
                  if (data.status === "success") {
                    popup(
                      "Successfully Copied!",
                      `Commit ID: ${data.commit_id}`,
                      "success",
                      "OK",
                      false
                    ).then((result) => {
                      if (result.isConfirmed) {
                        loadFileViewData();
                        numFiles = 1;
                        fileHistoryTracking(
                          data.from_commit_id,
                          data.commit_id,
                          backlogId,
                          sprintId,
                          branchRight,
                          numFiles,
                          branchLeft
                        );
                      }
                    });
                  } else {
                    popup(
                      "Something Wrong !",
                      `Error : ${data.message}`,
                      "error",
                      "OK",
                      false
                    );
                  }
                })
                .catch((err) => {
                  console.error("Error saving changes:", err);
                });
            } else {
              popup(
                "Commit Message Required",
                "Please provide a commit message.",
                "warning",
                "OK",
                false
              );
            }
          });
        }
      });
    } else {
      popup(
        "Not authorize to update..!",
        "Need to set authority on Environment setup page",
        "error",
        "OK",
        false
      );
    }
  });
}

/**
 * @abstract    Tracks file changes and logs history for commit tracking
 */
function fileHistoryTracking(
  from_commit_ids,
  response,
  backlog,
  sprint,
  branch,
  num_of_files,
  from_branch
) {
  var postData = [];
  var content = {
    from_commit_id: from_commit_ids,
    to_commit_id: response,
    backlog_id: backlog,
    sprint_id: sprint,
    to_branch: branch,
    from_branch: from_branch,
    num_of_files: num_of_files,
  };
  postData.push(content);
  const jsonString = JSON.stringify(postData);
  $.ajax({
    url: baseUrl + "release-management/commitHistoryInsert",
    type: "POST",
    data: jsonString,
    dataType: "json",
    success: function (response) {
      if (response.status !== "success") {
        popup(
          "Failed to Insertion",
          "An error occurred while inserting data to table",
          "error",
          "OK",
          false
        );
      }
    },
    error: function () {
      popup(
        "Failed response while insertion",
        "An error occurred while inserting data to table",
        "error",
        "OK",
        false
      );
    },
  });
}

/**
 * @abstract    to reduce the swal fire call by custom function
 */
function popup(title, text, icon, confirmButtonText, loading, iconHtml = null) {
  const config = {
    title: title,
    text: text,
    icon: icon,
    confirmButtonText: confirmButtonText || "OK", // Default to 'OK' if not provided
    iconHtml: iconHtml || null, // Use custom iconHtml if provided
  };

  if (loading) {
    config.allowOutsideClick = false; // Prevent closing on outside click
    config.allowEscapeKey = false; // Prevent closing with Escape key
    config.didOpen = () => {
      Swal.showLoading(); // Show loading spinner
    };
  }

  return Swal.fire(config);
}
