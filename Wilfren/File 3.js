const statusPath =
  `${baseUrl}release-management/statusUpdate`;
const memcachePath =
  `${baseUrl}release-management/memcacheClear`;
var freeTheResource = false;
var cherryPickedFlag = false;
$(document).ready(function () {
  // Handle form submission via AJAX
  $("#toCommit").submit(async function (event) {
    event.preventDefault(); // Prevent form from submitting normally

    var formData = new FormData(this);
    formData.append(
      "branch",
      document.getElementById("branch").getAttribute("data-branch")
    );
    const commits = formData.getAll("commitIds[]");
    const toggleCheck = document.getElementById("toggleCheck");
    //Separating the files selected
    const selectedFiles = [];
    document.querySelectorAll('.file-item input[type="checkbox"]:checked').forEach(checkbox => {
      const fileName = checkbox.closest('.file-item').dataset.file;
      const commitId = checkbox.closest('.file-item').dataset.commitId;
      selectedFiles.push({ file: fileName, commitId: commitId });
    });
console.log(overallData);
console.log(commits);
console.log(selectedFiles);
formData.append("particularFiles", JSON.stringify(selectedFiles));console.log(typeof (formData));
    if (commits.length > 0 || selectedFiles.length > 0) {
      if (toggleCheck.checked ? 1 : 0) {
        dependencyCheck(overallData,commits)
        .then(async ()=>{console.log("hai");
          // Show loading SweetAlert while processing request
          Swal.fire({
            iconHtml:
              '<i class="bi-bug" style="font-size: 3rem; color: red;font-weight:bolder;"></i>',
            title: "Checking Syntax of the Commit ID",
            text: "Hold on we are checking your syntax",
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            },
          });
          if(commits.length > 0){console.log("45");
            const parseResponse = await ajaxRequest(formData, "parse");console.log(parseResponse,"46");
            if (parseResponse) {console.log("47");
              Swal.close();console.log("51");
              //Response is calculated if has error or not
              hasErrorOrNot(parseResponse,commits,formData);console.log("53");
            } else {
              Swal.fire({
                icon: "error",
                title: "Request to the Server Failed",
                text: "An error occurred while connecting with Server. Please try again later",
                confirmButtonText: "OK",
              });
            }
          }
          else if(selectedFiles.length > 0){console.log("Else part");
            const parseResponse = await parseCheckForFiles(formData);console.log(parseResponse,"64");
            Swal.close();console.log(parseResponse);
            hasErrorOrNot(parseResponse,commits,formData);
          }
        })
        // .catch(()=>{
        //   Swal.fire({
        //     icon: "error",
        //     title: "Some Commits have dependencies",
        //     text: "You have selected the commit ids that are having dependencies of other commits so proceed that commits manually",
        //   });
        // });
      } else {
        afterSyntaxCheck(commits, formData);
      }
    } else {
      Swal.fire({
        icon: "error",
        title: "Commit ID not Selected",
        text: "Please select a Commit ID",
      });
    }
  });
});

function hasErrorOrNot(parseResponse,commits,formData){console.log(parseResponse);
  if (parseResponse.status == "Not empty") {
    // Trigger the modal with the simulated response data
    Swal.fire({
      icon: "error",
      title: "Sorry you have Syntax Error",
      text: "Syntax Error Occurred in the commit id you are proceeding with please check or remove the commit id from moving",
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: "Show the Error",
      cancelButtonText: "Cancel",
      allowOutsideClick: false,
      allowEscapeKey: false,
      reverseButtons: true,
    }).then((result) => {
      if (result.isConfirmed) {
        let syntaxModalOutput = openSyntaxErrorModal(
          parseResponse.message
        );
      } else if (result.isDismissed) {
        Swal.close();
      }
    });
  } else if (parseResponse.status == "Empty") {
    Swal.fire({
      icon: "success",
      title: "Congrats No Syntax Error On Commiting Files",
      text: "Do you want to Proceed Futher ",
      showCancelButton: true,
      showConfirmButton: true,
      cancelButtonText: "Cancel",
      confirmButtonText: "Proceed for Cherry Pick",
      allowOutsideClick: false,
      reverseButtons: true,
    }).then((result) => {
      if (result.isConfirmed) {
        afterSyntaxCheck(commits, formData);
      } else {
        Swal.close();
      }
    });
  } else {
    Swal.fire({
      icon: "error",
      title: "Syntax Error status error",
      text: "An error occurred. Please try again later or turn off the option.",
      confirmButtonText: "OK",
    });
  }
}

async function parseCheckForFiles(formData){
  return new Promise(async (resolve,reject) => {console.log("141");
    var response = await ajaxRequest(formData, "parse");
    if(response){
      resolve(response);
    }
    else{
      reject();
    }
  });
}

// Checkbox interactions
const mainCheckboxes = document.querySelectorAll(".main-checkbox");
const commitItems = document.querySelectorAll(".commit-item");

// Card selection logic
// Card selection logic
function select(key) {
  const card = document.getElementById("card_" + key);
  const cardCheckbox = document.getElementById("total_select_" + key);
  const fileItems = document.querySelectorAll(`#card_${key} .file-item`);

  if (card.classList.contains("selected")) {
      // If card is selected, deselect it and all files
      card.classList.remove("selected");
      cardCheckbox.checked = false;
      fileItems.forEach(item => {
          const checkbox = item.querySelector('input[type="checkbox"]');
          checkbox.checked = false;
          checkbox.classList.add('d-none');
          item.classList.remove('bg-success');
          item.querySelector('.file-name').classList.remove('text-white');
      });
  } else {
      // If card is not selected, select it and deselect all files
      card.classList.add("selected");
      cardCheckbox.checked = true;
      fileItems.forEach(item => {
          const checkbox = item.querySelector('input[type="checkbox"]');
          checkbox.checked = false; // Uncheck all files
          checkbox.classList.add('d-none');
          item.classList.remove('bg-success');
          item.querySelector('.file-name').classList.remove('text-white');
      });
  }
}

commitItems.forEach((item) => {
  item.addEventListener("click", function () {
    const commitId = item.getAttribute("data-sha");
    handleRowClick(commitId);
  });
});

// Function to handle the commit details (diffs, etc.)
function handleRowClick(commitId) {
  Swal.fire({
    iconHtml:
      '<i class="bi-intersect" style="font-size: 3rem; color: green;"></i>',
    title: "Getting the Difference in the Commit",
    text: "Processing your request...Please Wait",
    allowOutsideClick: false, // Prevent closing on outside click
    didOpen: () => {
      Swal.showLoading(); // Show loading spinner
    },
  });

  $.ajax({
    url: `${baseUrl}release-management/commitdetails`,
    type: "POST",
    contentType: "application/json",
    data: JSON.stringify({ commit: commitId }),
    async: false,
    success: function (response) {
      Swal.close();
      // Use the response as the diff string
      const diffString = response;
      const targetElement = document.getElementById("myDiffElement");
      const configuration = {
        drawFileList: true,
        fileListToggle: false,
        fileListStartVisible: false,
        fileContentToggle: false,
        matching: "lines",
        outputFormat: "side-by-side",
        synchronisedScroll: true,
        highlight: true,
        renderNothingWhenEmpty: false,
      };

      const diff2htmlUi = new Diff2HtmlUI(
        targetElement,
        diffString,
        configuration
      );
      diff2htmlUi.draw();
      diff2htmlUi.highlightCode();

      const modal = new bootstrap.Modal(document.getElementById("filedetails"));
      modal.show();console.log(diffString);
    },
    error: function (jqXHR, textStatus, errorThrown) {
      console.error("Error with AJAX request:", textStatus, errorThrown);
      Swal.fire({
        icon: "error",
        title: "Request Failed",
        text: "An error occurred while processing the request. Please try again.",
      });
    },
  });
}

async function callJenkinsAPI(commitId, backlog, sprint, branch) {
  return new Promise((resolve, reject) => {
    // Create a Promise
    $.ajax({
      url: `${baseUrl}release-management/jenkins`,
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({
        commit_id: commitId,
        backlog: backlog,
        sprint: sprint,
        branch: branch,
      }),
      async: false,
      success: function (response) {
        // // Handle success here
        // console.log("AJAX request successful:", response);
        // alert("Success");
        const data = JSON.parse(response);
        // Get the current path and calculate the next group number
        const path = window.location.pathname;
        const currentGroupNumber = parseInt(path.split("/").pop(), 10);
        const nextGroupNumber = currentGroupNumber + 1;
        const newUrl = path.replace(currentGroupNumber, nextGroupNumber);

        // Determine if the "Proceed to next environment" button should appear
        let showConfirmButton = currentGroupNumber < data[0].pages;
        let confirmButtonText = "Proceed to next environment";
        let cancelButtonText =
          currentGroupNumber < data[0].pages
            ? "Move remaining Commits"
            : "Done";
        // cherryPickedFlag = true;
        // Show the SweetAlert modal
        // memcacheReset();
        freeTheStatus();
        Swal.fire({
          icon: "success",
          title: "Moved To Server Successfully",
          text: "You are Proceeding to home page",
          showConfirmButton: true,
          showCancelButton: false,
          confirmButtonText: "OK",
          allowOutsideClick: false,
          allowEscapeKey: false,
        }).then((result) => {
          if (result.isConfirmed) {
            // Redirect to the new URL only if "Proceed to next environment" was shown
            window.location.href = `${baseUrl}release-management/commitstatus`;
          }
        });
      },
      error: function (jqXHR, textStatus, errorThrown) {
        // Handle error here
        console.error("Error with Jenkins API call:", textStatus, errorThrown);
        reject(errorThrown); // Reject the Promise with the error
      },
    }).always(function () {
      // This block executes after the AJAX request completes (success or error)
      resolve(); // Resolve the Promise
    });
  });
}

const handleRow = (commitId,filewiseCommitId) => {
  return new Promise((resolve, reject) => {
    Swal.fire({
      iconHtml:
        '<i class="bi-intersect" style="font-size: 3rem; color: green;"></i>',
      title: "Getting the Difference in the Commit",
      text: "Processing your request...Please Wait",
      allowOutsideClick: false, // Prevent closing on outside click
      didOpen: () => {
        Swal.showLoading(); // Show loading spinner
      },
    });

    $.ajax({
      url: `${baseUrl}release-management/commitdetails`,
      type: "POST",
      contentType: "application/json",
      data: JSON.stringify({ commit: commitId, particularFiles: filewiseCommitId}),
      success: function (response) {
        Swal.close();

        // Extract only the diff HTML content from the response
        const diffString = response; // Assuming the diff content is stored in `response.diffContent`

        if (!diffString) {
          console.error("No diff content found in the response.");
          reject("Error loading diff content.");
          return;
        }

        // Return the diff content for insertion into the modal
        resolve(diffString);
      },
      error: function (jqXHR, textStatus, errorThrown) {
        console.error("Error with AJAX request:", textStatus, errorThrown);
        reject("Error fetching commit details.");
      },
    });
  });
};

// Function to open the modal with error data
function openSyntaxErrorModal(response) {
  // Get the commit list and error details container elements
  const commitList = document.getElementById("error-commit-list");
  const errorDetails = document.getElementById("file-error-content");

  // Clear previous content
  commitList.innerHTML = "";
  errorDetails.innerHTML = "";

  // Loop through the response object
  for (const [commitId, files] of Object.entries(response)) {
    if (Object.keys(files).length === 0) continue; // Skip empty commits

    // Create a commit item
    const commitItem = document.createElement("li");
    commitItem.innerHTML = `Commit ID: <b>${commitId}</b>`;
    commitItem.style.cursor = "pointer";

    // Create a nested list for files with errors
    const fileList = document.createElement("ul");
    for (const [fileName, details] of Object.entries(files)) {
      const fileItem = document.createElement("li");
      fileItem.innerHTML = `<span style="color:red;text-decoration:underline;">${fileName}</span>`;
      fileItem.style.cursor = "pointer";
      commitItem.classList.add("commit-item");

      // Store details in the element to display on click
      fileItem.dataset.errorDetails = JSON.stringify(details, null, 2);
      fileItem.onclick = function () {
        showErrorDetails(fileName, this.dataset.errorDetails);
      };

      fileList.appendChild(fileItem);
    }

    commitItem.appendChild(fileList);
    commitList.appendChild(commitItem);
  }

  // Show the modal
  const syntaxModal = new bootstrap.Modal(
    document.getElementById("syntaxErrorModal")
  );
  syntaxModal.show();
  return syntaxModal;
}

// Function to display error details for a selected file
function showErrorDetails(fileName, details) {
  const errorDetails = document.getElementById("file-error-content");
  const errorData = JSON.parse(details);

  // Determine the color based on the content
  const syntaxErrorClass =
    errorData.syntax_error === "No syntax errors."
      ? "no-error"
      : "error-message";
  const functionStatusClass = errorData.function_in_loop.includes(
    "Function 'filewrite' is inside a loop"
  )
    ? "function-in-loop-error"
    : "function-status";

  // Build error content with conditional styling
  const content = `
        <h6 class="file-name">File: ${fileName}</h6>
        <p><span class="label">Syntax Error:</span> <span class="${syntaxErrorClass}">${errorData.syntax_error}</span></p>
        <p><span class="label">Function in Loop:</span> <span class="${functionStatusClass}">${errorData.function_in_loop}</span></p>
        <p><span class="label">Error Flag:</span> <span class="error-flag">${errorData.error}</span></p>
    `;
  errorDetails.innerHTML = content;
}

function historyTracking(
  response,
  backlog,
  sprint,
  branch,
  num_of_files,
  from_branch
) {
  //from the response segregate newid, commit_id, branch,
  var postData = [];console.log(response);
  response.forEach((item) => {
    var content = {
      from_commit_id: item.commit_id,
      to_commit_id: item.newid,
      backlog_id: backlog,
      sprint_id: sprint,
      to_branch: branch,
      from_branch: from_branch,
      num_of_files: num_of_files,
    };
    postData.push(content);
  });
  const jsonString = JSON.stringify(postData);
  $.ajax({
    url: baseUrl + "release-management/commitHistoryInsert",
    type: "POST",
    data: jsonString,
    dataType: "json",
    success: function (response) {
      if (response.status !== "success") {
        Swal.fire({
          icon: "error",
          title: "Failed to Insertion",
          text: "An error occurred while inserting data to table",
          confirmButtonText: "OK",
        });
      }
    },
    error: function () {
      Swal.fire({
        icon: "error",
        title: "Failed response while insertion",
        text: "An error occurred while inserting data to table",
        confirmButtonText: "OK",
      });
    },
  });
}

function afterSyntaxCheck(commits, formData) {console.log(commits);
  Swal.fire({
    iconHtml:
        '<i class="bi-intersect" style="font-size: 3rem; color: green;"></i>',
      title: "Getting the Difference in the Commit",
      text: "Processing your request...Please Wait",
      allowOutsideClick: false, // Prevent closing on outside click
      didOpen: () => {
        Swal.showLoading(); // Show loading spinner
      },
  });
  // Custom SweetAlert confirmation dialog with HTML content
  const showCherryPickConfirmation = async (commits) => {
    // Populate the selected commits list
    const commitsList = document.getElementById("selected-commits-list");
    commitsList.innerHTML = commits
      .map((commit) => `<li>${commit}</li>`)
      .join("");
    await loadCommitDetails(commits,formData.getAll("particularFiles"));
    // Show the custom modal
    const cherryPickModal = new bootstrap.Modal(
      document.getElementById("cherryPickModal")
    );
    Swal.close();
    cherryPickModal.show();
    
    // Load commit details
    document
      .getElementById("cancel-btn")
      .addEventListener("click", function () {
        cherryPickModal.hide();
      });

    document
      .getElementById("view-diff-btn")
      .addEventListener("click", async () => {
        const diffViewer = document.getElementById("diff-viewer");
        diffViewer.innerHTML = "Loading diff...";
        const commitIds = commits;
        // Fetch diff content from the server
        const ajaxData = {
          commitIds: commitIds,
          branch: document.getElementById("branch").getAttribute("data-branch"),
          particularFiles: formData.getAll("particularFiles"),
        };
        $.ajax({
          url: serverPath,
          type: "POST",
          data: ajaxData,
          beforeSend: function (xhr) {
            xhr.setRequestHeader("Action", "merge");
          },
          success: function (response) {
            if (response) {
              response.forEach((responses) => {
                const diffContent = responses; // Assuming the server returns raw diff content
                const diffHtml = Diff2Html.html(diffContent, {
                  inputFormat: "diff",
                  showFiles: false,
                  matching: "lines",
                  outputFormat: "line-by-line",
                });
                diffViewer.innerHTML = diffHtml;
              });
            } else {
              diffViewer.innerHTML = "No diff content available.";
            }
          },
          error: function () {
            Swal.fire({
              icon: "error",
              title: "Sorry Cannot Fetch Merged Content",
              text: "Sorry Some Issue has been Occurred Please try the Merged content after some time",
            });
          },
        });
      });
    document.getElementById("mergeClose").addEventListener("click", function () {
      afterSyntaxCheck(commits, formData);
    });
    document.getElementById("mergeClose2").addEventListener("click", function () {
      afterSyntaxCheck(commits, formData);
    });

    // Ensure the event listener for confirmation button is added
    const confirmButton = document.getElementById("confirm-btn");
    confirmButton.addEventListener(
      "click",
      async function confirmClickHandler() {
        // Close the modal
        cherryPickModal.hide();
        // Unregister the event listener after first execution to avoid duplicates
        confirmButton.removeEventListener("click", confirmClickHandler);
        // Retrieve formData values for cherry-picking
        const from_branch = document
          .getElementById("from_branch")
          .getAttribute("data-from_branch");
        const backlog = document
          .getElementById("backlog_id")
          .getAttribute("data-backlog_id");
        const sprint = document
          .getElementById("sprint_id")
          .getAttribute("data-sprint_id");
        const branch = document
          .getElementById("branch")
          .getAttribute("data-branch");
        const num_of_files = document
          .getElementById("num_of_files")
          .getAttribute("data-num_of_files");
        Swal.fire({
          iconHtml:
            '<i class="bi-database-fill-gear" style="font-size: 3rem; color: blue;font-weight:bolder;"></i>',
          title: "Checking Resource Availability",
          text: "Proccessing .... Please wait",
          allowOutsideClick: false,
          allowEscapeKey: false,
          didOpen: () => {
            Swal.showLoading();
          },
        });
        var operation = "Occupied";
        //Insert into table as occupied
        var response = await ajaxRequest({ status: operation }, null);
        if (response) {
          var result = JSON.parse(response);
          if (result.status == "success") {
            console.log("executed in if success");
            freeTheResource = true;
            // Show loading SweetAlert while processing request
            Swal.fire({
              iconHtml:
                '<i class="bi-github" style="font-size: 2rem; color: grey;"></i>',
              title: "Fetching the details of the Commit Id",
              text: "Working on the Commit Id  .... Please Wait",
              allowOutsideClick: false,
              allowEscapeKey: false,
              didOpen: () => {
                Swal.showLoading();
              },
            });
            var response = await ajaxRequest(formData, "check");
            if (response) {
              Swal.close();
              // Check if the commit has already been cherry-picked
              if (response.status === "Commit has been cherry-picked") {
                historyTracking(
                  response.data,
                  backlog,
                  sprint,
                  branch,
                  num_of_files,
                  from_branch
                );
                Swal.close();
                // alert("Jenkins call area");
                await callJenkinsAPI(
                  response["data"][0].newid,
                  backlog,
                  sprint,
                  branch
                );
                // alert ("gfg");
                // alert("hai");

                freeTheStatus();
              } else if (response.status === "confirmation for cherry pick") {
                Swal.fire({
                  title: "Are you sure?",
                  text: "The Selected Commit Id is not been Merged in GitHub itself",
                  icon: "warning",
                  showCancelButton: true,
                  confirmButtonText: "Yes, cherry pick!",
                  cancelButtonText: "No, cancel!",
                  reverseButtons: true,
                }).then(async (result) => {
                  if (result.isConfirmed) {
                    Swal.close();
                    var commitLength = formData.getAll("commitIds[]").length;
                    var commitsIds = formData.getAll("commitIds[]");
                    var commitMessage = [];
                    for (i = 0; i < commitLength; i++) {
                      var msg = null;
                      Object.entries(array).forEach(([key, value]) => {
                        if (key === commitsIds[i]) {
                          msg = value[0]["commit_message"];
                        }
                      });
                      var cmtMsg = null;
                      cmtMsg = await commitMessageInput(msg);
                      commitMessage.push(cmtMsg);
                    }
                    // var fileSelectedLength = formData.getAll("particularFiles").length;
                    var fileSelectedBeforeParse = formData.getAll("particularFiles");
                    var fileSelectedAfterParse =[];
                    for(var parsingValue of fileSelectedBeforeParse){
                      fileSelectedAfterParse.push(JSON.parse(parsingValue));
                    }
                    var fileCommitMsg = [];
                    const fileSelected = fileSelectedAfterParse[0].map(item => item.commitId);
                    console.log(fileSelected.length);
                    console.log(fileSelectedAfterParse);
                    console.log(fileSelected);
                    for (const fileCommit of fileSelected) {
                      var msg = null;
                      Object.entries(array).forEach(([key, value]) => {
                        if (key === fileCommit) {
                          msg = value[0]["commit_message"];
                        }
                      });
                      var cmtMsg = null;console.log(msg);
                      cmtMsg = await commitMessageInput(msg);
                      fileCommitMsg.push({[fileCommit] : cmtMsg});
                    }
                    if (commitMessage || fileCommitMsg) {
                      Swal.fire({
                        iconHtml:
                          '<i class="bi-bezier2" style="font-size: 2rem; color: forestgreen;"></i>',
                        title: "Cherry Pick Proccess is Going On",
                        text: "Please wait while we are proccessing your request",
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => {
                          Swal.showLoading();
                        },
                      });
                      if(commitMessage)
                        formData.append("message", commitMessage);
                      if(fileCommitMsg)
                        formData.append("fileCommitMsg", JSON.stringify(fileCommitMsg));
                      var response = await ajaxRequest(
                        formData,
                        "confirmed cherry-pick"
                      );
                      if (response) {
                        Swal.close();
                        if (
                          response.status === "Commit has been cherry-picked"
                        ) {
                          historyTracking(
                            response.data,
                            backlog,
                            sprint,
                            branch,
                            num_of_files,
                            from_branch
                          );
                          Swal.close();
                          await callJenkinsAPI(
                            response["data"][0].newid,
                            backlog,
                            sprint,
                            branch
                          );
                        } else if (
                          response.status == "Syntax Error After Cherry Pick"
                        ) {
                          Swal.close();
                          Swal.fire({
                            title: "Syntax Error After Merging",
                            text: "Please check the syntax",
                            icon: "warning",
                            showCancelButton: true,
                            showConfirmButton: true,
                            confirmButtonText: "Show the Error",
                            cancelButtonText: "Cancel",
                            allowOutsideClick: false,
                            reverseButtons: true,
                            allowEscapeKey: false,
                          }).then((result) => {
                            if (result.isConfirmed) {
                              openSyntaxErrorModal(
                                response.response["message"]
                              );
                            } else if (result.isDismissed) {
                              Swal.close();
                            }
                          });
                          freeTheStatus();
                        } else {
                          Swal.close();
                          Swal.fire({
                            title: "Error",
                            text:
                              response.message +
                              " Failed Cherry Pick. Please try again later.",
                            icon: "error",
                            confirmButtonText: "OK",
                          });
                          freeTheStatus();
                        }
                      } else {
                        Swal.fire({
                          icon: "error",
                          title: "Submission Failed",
                          text: "An error occurred while submitting the form. Please try again.",
                          confirmButtonText: "OK",
                        });
                      }
                      freeTheStatus();
                    }
                  } else {
                    Swal.close();
                    Swal.fire({
                      title: "Cancelled",
                      text: "This action has been aborted",
                      icon: "warning",
                      confirmButtonText: "OK",
                    });
                    freeTheStatus();
                  }
                });
              } else if (response.status == "Syntax Error After Cherry Pick") {
                Swal.close();
                openSyntaxErrorModal(response.response["message"]);
                freeTheStatus();
              } else {
                Swal.close();
                Swal.fire({
                  title: "Error Occurred",
                  text: response.message,
                  icon: "warning",
                  confirmButtonText: "OK",
                });
                freeTheStatus();
              }
            } else {
              Swal.fire({
                icon: "error",
                title: "Submission Failed",
                text: "An error occurred while submitting the form. Please try again.",
                confirmButtonText: "OK",
              });
              freeTheStatus();
            }
            freeTheResource = false;
          } else {
            console.log("executed in else error");
            Swal.fire({
              iconHtml:
                '<i class="bi-database-fill-lock" style="font-size: 2rem; color: red;"></i>',
              title: result.message,
              text: "Please try after some time",
              icon: "error",
              confirmButtonText: "OK",
            });
          }
        } else {
          Swal.fire({
            iconHtml:
              '<i class="bi-database-fill-lock" style="font-size: 2rem; color: red;"></i>',
            title: "Occupied resource",
            text: "Some other is accessing please try after some time",
            confirmButtonText: "OK",
          });
        }
      }
    );
  };

  // Load commit details function
  const loadCommitDetails = async (commitId,filewiseCommitId) => {console.log("Load Commit Dedtails Commit Id",commitId);
    const commitDetailsDiv = document.getElementById("commit-details");

    try {
      const modalContent = await handleRow(commitId,filewiseCommitId);
      const targetElement = document.getElementById("commit-details");

      // Configure Diff2Html
      const configuration = {
        inputFormat: "diff", // Ensure the input is treated as a diff string
        outputFormat: "side-by-side", // Choose 'side-by-side' or 'line-by-line' display
        matching: "lines", // Match lines for better visual clarity
        diffStyle: "word", // Highlight individual word changes
        drawFileList: true,
        fileListToggle: true,
        fileListStartVisible: true,
        fileContentToggle: false,
        synchronisedScroll: true,
        highlight: true,
        renderNothingWhenEmpty: false,
      };

      // Initialize and draw Diff2HtmlUI
      const diff2htmlUi = new Diff2HtmlUI(
        targetElement,
        modalContent,
        configuration
      );
      diff2htmlUi.draw(); // Draws the diff content
      diff2htmlUi.highlightCode(); // Highlights syntax for added clarity

      // Resolve the HTML content if you need it in the function's return
      return targetElement.innerHTML;
    } catch (error) {
      commitDetailsDiv.innerHTML = "<p>Error loading commit details.</p>";
    }
  };

  // Show confirmation dialog and handle cherry-pick
  showCherryPickConfirmation(commits);
}

function freeTheStatus() {
  var operation = "Free";
  //Insert into table as occupied
  $.ajax({
    url: statusPath,
    type: "POST",
    data: { status: operation },
    success: function (response) {
      var results = JSON.parse(response);
      if (results.status == "success") {
        return true;
      }
    },
    error: function () {
      Swal.fire({
        icon: "error",
        title: "Resource Free Error",
        text: "Cannot Free the resource",
        confirmButtonText: "OK",
      });
    },
  });
}
async function memcacheReset() {
  //Insert into table as occupied
  $.ajax({
    url: memcachePath,
    type: "GET",
    success: function (response) {
      var results = JSON.parse(response);
      if (results.status == "success") {
        return true;
      }
    },
    error: function () {
      Swal.fire({
        icon: "error",
        title: "Memcache Not Cleared",
        text: "Cannot Clear the Memcache",
        confirmButtonText: "OK",
      });
    },
  });
}

function ajaxRequest(data, headers) {
  return new Promise((resolve, reject) => {
    if (headers == "confirmed cherry-pick") {
      $.ajax({
        url: serverPath,
        type: "POST",
        data: data,
        contentType: false,
        processData: false,
        beforeSend: function (xhr) {
          xhr.setRequestHeader("cherry-pick", headers);
          xhr.setRequestHeader("Action", "check");
        },
        success: function (response) {
          resolve(response);
        },
        error: function () {
          resolve(null);
        },
      });
    } else if (headers != null) {
      $.ajax({
        url: serverPath,
        type: "POST",
        data: data,
        contentType: false,
        processData: false,
        beforeSend: function (xhr) {
          xhr.setRequestHeader("Action", headers);
        },
        success: function (response) {
          resolve(response);
        },
        error: function () {
          resolve(null);
        },
      });
    } else if (headers == null) {
      $.ajax({
        url: statusPath,
        type: "POST",
        data: data,
        success: function (response) {
          resolve(response);
        },
        error: function () {
          resolve(null);
        },
      });
    }
  });
}

window.addEventListener("beforeunload", (event) => {
  if (freeTheResource) {
    freeTheStatus();
    event.preventDefault();
    event.returnValue = "";
    freeTheResource = false;
  }
});

async function commitMessageInput(msg) {
  return new Promise((resolve, reject) => {
    Swal.fire({
      title: "Enter Commit Message",
      input: "text",
      input: "text",
      html: `
      <label style="display: block; margin-bottom: 10px;">
        Commit Message for <span style="font-weight: bold; color: black;font-size: 20px;">${msg}</span>
      </label>
    `,
      inputPlaceholder: "Enter your commit message here...",
      inputValidator: (value) => {
        if (!value) {
          return "You need to enter a messeage!";
        }
      },
      footer: `<span style="color: red;">Note: If this commit is already in the target branch, the commit message will not be considered.</span>`,
      showCancelButton: true,
      showConfirmButton: true,
      confirmButtonText: "Submit",
      allowOutsideClick: false,
      allowEscapeKey: false,
      reverseButtons: true,
    }).then((commitResult) => {
      if (commitResult.isConfirmed && commitResult.value.trim()) {
        const commitMessage = commitResult.value.trim();
        resolve(commitMessage);
      } else if (commitResult.isDismissed) {
        freeTheStatus();
      }
    });
  });
}

function dependencyCheck(commitData,commitIds){
  return new Promise((resolve,reject)=>{
    // resolve();
    const dependencies = []; // Initialize as an array to store commit IDs with dependencies

    commitIds.forEach(commitId => {
        // Access the object corresponding to commitId
        if (commitId in commitData && 'dependency' in commitData[commitId]) {
            dependencies.push(commitId);
            console.log(`Commit ${commitId} has a 'dependency' key.`);
        }
    });
    if(dependencies.length==0){
      console.log("No depedency");
      resolve();
    }
    else{
      console.log("Have Dependency");
      reject();
    }
  });
}

// Add a click event listener to open the modal programmatically
// document.getElementById('cherryPickFileBtn').addEventListener('click', function () {

  
  // Trigger Bootstrap's modal open function
  // let fileCherryPickModal = new bootstrap.Modal(document.getElementById('fileCherryPickModal'));
  // fileCherryPickModal.show();
      // Add click event to file items
      // File item selection logic
// File item selection logic
document.querySelectorAll('.file-item').forEach(item => {
  item.addEventListener('click', function (event) {
      // Prevent the event from propagating to the card
      event.stopPropagation();

      const checkbox = this.querySelector('input[type="checkbox"]');
      const fileNameSpan = this.querySelector('.file-name');
      const card = this.closest('.card');
      const cardKey = card.id.split('_')[1];
      const cardCheckbox = document.getElementById("total_select_" + cardKey);
      const fileItems = card.querySelectorAll('.file-item');

      // Toggle file selection
      checkbox.checked = !checkbox.checked;
      checkbox.classList.toggle('d-none', !checkbox.checked);

      if (checkbox.checked) {
          this.classList.add('bg-success');
          fileNameSpan.classList.add('text-white');
      } else {
          this.classList.remove('bg-success');
          fileNameSpan.classList.remove('text-white');
      }

      // Check if all files are selected
      const allFilesSelected = Array.from(fileItems).every(item => 
          item.querySelector('input[type="checkbox"]').checked
      );

      // If all files are selected, select the card and unselect all files
      if (allFilesSelected) {
          card.classList.add("selected");
          cardCheckbox.checked = true;

          // Unselect all files
          fileItems.forEach(item => {
              const checkbox = item.querySelector('input[type="checkbox"]');
              checkbox.checked = false;
              checkbox.classList.add('d-none');
              item.classList.remove('bg-success');
              item.querySelector('.file-name').classList.remove('text-white');
          });
      } else {
          // If not all files are selected, deselect the card
          card.classList.remove("selected");
          cardCheckbox.checked = false;
      }
  });
});
  // Cherry Pick File Button
  document.getElementById('cherryPickFileBtnSubmit').addEventListener('click', function () {
    const selectedFiles = [];
    document.querySelectorAll('.file-item input[type="checkbox"]:checked').forEach(checkbox => {
        const fileName = checkbox.closest('.file-item').dataset.file;
        const commitId = checkbox.closest('.file-item').dataset.commitId;
        selectedFiles.push({ file: fileName, commitId: commitId });
    });
    if(selectedFiles.length!=0){
      var formData = new FormData();
      formData.append("files", JSON.stringify(selectedFiles));console.log(typeof (formData));
      formData.append("targetBranch",document.getElementById("branch").getAttribute("data-branch"));
      $.ajax({
          url: serverPath,
          type: "POST",
          data: formData,
          contentType: false,
          processData: false,
          beforeSend: function (xhr) {
              xhr.setRequestHeader("Action", "fileCherryPick");
          },
          success: function (response) {
              Swal.fire({
                title: "Request Successful",
                text: "Data sent successfully!",
                icon: "success",
                confirmButtonText: "OK",
                allowEscapeKey: false,
                allowOutsideClick: false,
              });
          },
          error: function () {
              Swal.fire({
                title: "Request Failed",
                text: "Error in sending data",
                icon: "warning",
                confirmButtonText: "OK",
                allowEscapeKey: false,
                allowOutsideClick: false,
              });
          },
      });
    }
    else{
      Swal.fire({
        title: "No File Selected",
        text: "Please select a file",
        icon: "warning",
        confirmButtonText: "OK",
        allowEscapeKey: false,
        allowOutsideClick: false,
      });
    }

    // Log selected files to the console
    console.log('Selected Files:', selectedFiles);
  });
// });

