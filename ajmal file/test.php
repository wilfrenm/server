<?php
header('Content-Type: application/json');
$headers = getallheaders();
$path = 'C:/xampp/htdocs/Git-check/server2/server/server';
chdir($path);
$memcache = new Memcache();
if (!$memcache->connect('localhost', 11211)) {
    die("Could not connect to Memcache");
}
/**
 * --------------------------------------------------------------------------------------------------
 */
// // print_r("hello");die;
// // $headers['cherry-pick']='confirm cherry-pick';
// // print_r($headers);die;
// shell_exec('chmod -R 0777 /home/Staging/jenkin/AgencyDirect/');

// // chdir("/home/Staging/AgencyDirect");
// // shell_exec('sudo su');
// chdir("/home/Staging/jenkin/AgencyDirect");
// shell_exec('chmod -R u+w /home/Staging/jenkin/AgencyDirect/.git/logs');
// shell_exec('chmod -R u+w /home/Staging/jenkin/AgencyDirect/.git/refs');

// if (shell_exec('git config --get user.email') == '') {
//     shell_exec('git config --global --add user.email "jagannathan.m@infinitisoftware.net"');
// }
// // shell_exec('git config --add user.email "jagannathan.m@infinitisoftware.net"');

// // echo shell_exec('git config --get user.name 2>&1');
// if (shell_exec('git config --get user.name') == '') {
//     shell_exec('git config --global --add user.name "jagan"');
// }
// // shell_exec('git config --add user.name "jagan"');

// // putenv('HOME=/home/www-data');

// // echo shell_exec('echo $HOME');

// // shell_exec('git config --global --add safe.directory /home/Staging/jenkin/AgencyDirect 2>&1');

// // echo shell_exec('git reset --hard origin');
// shell_exec('git stash');
// shell_exec('git stash clear');
// // echo shell_exec('git config --list');
// // die;
// // echo shell_exec("git branch");

// // echo shell_exec('git pull -f '.$repositoryUrl.' 2>&1');
// shell_exec('git pull 2>&1');
// // die;

// //echo shell_exec('git config -l');

// // die;
//--------------------------------------------------------------------------------------------------------------------------------------------------

if (isset($headers['Action']) && $headers['Action'] === 'check') {
    gitHubCheck($headers);
} else if (isset($headers['Action']) && $headers['Action'] == 'parse') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_POST;
        // Change directory to the Git repository
        // chdir($path);
        // chdir("/home/Staging/jenkin/AgencyDirect");
        $commitIds = $postData['commitIds'];
        $branch = $postData['branch'];
        $result = json_encode(parseCheck($commitIds, $branch));
        echo $result;
    }
} else if (isset($headers['Action']) && $headers['Action'] === "merge") {
    $postData = $_POST;
    // print_r($_POST);
    // $data=simulateMergeContent('C:\xampp\htdocs\Git-check\server2\server',["078757c570d9b6e3d7e5a31e87d61f049bbee77e","8e673916a550fdf7d7b9abe14b47f218f313d175"],"test");
    $data = simulateMergeContent($path, $postData['commitIds'], $postData['branch']);
    echo json_encode($data);
} else if (isset($headers['Action']) && $headers['Action'] === 'query') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_POST;
        $postSprintId = $postData['sprintId'];
        $postBacklogId = $postData['backlogId'];
        try {
            // Database credentials
            $host = 'localhost'; // Replace with your database host
            $dbname = 'jenkin'; // Replace with your database name
            $username = 'root'; // Replace with your database username
            $password = '1234'; // Replace with your database password

            // Create PDO connection
            $conn = new PDO("mysql:host=$host;dbname=$dbname;", $username, $password);

            // Set PDO error mode to exception
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // callData();
            $data = getJenkinData($postBacklogId, $postSprintId);

            // Return the data as JSON response
            $groupedData = gitCheck($data);
            // print_r($groupedData);
            // die;
            echo json_encode($groupedData);
        } catch (PDOException $e) {
            // In case of error, show the error message
            echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
        }
    }
} elseif (isset($headers['Action']) && $headers['Action'] === 'file') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['branchLeft'])) {
        // Get POST data
        $postData = $_POST;

        $branchLeft = $postData['branchLeft'];
        $branchRight = $postData['branchRight'];
        $postSprintId = $postData['sprintId'];
        $postBacklogId = $postData['backlogId'];

        $isValidBranch = validBranch($branchLeft, $branchRight);

        if ($isValidBranch) {
            // Compare branches
            $results = compareBranches($branchLeft, $branchRight, $postSprintId, $postBacklogId);

            if (isset($results['status']) && $results['status'] === 'error') {
                echo json_encode([
                    'status' => 'error',
                    'message' => $results['message'],
                ]);
            } else if (isset($results['message'])) {
                echo json_encode($results);
            } else {
                echo json_encode($results, true);
            }
        }
    }
} elseif (isset($headers['Action']) && $headers['Action'] === 'particularFile') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = json_decode(file_get_contents('php://input'), true);

        $branchLeft = isset($postData['branchLeft']) ? $postData['branchLeft'] : null;
        $branchRight = isset($postData['branchRight']) ? $postData['branchRight'] : null;
        $file = isset($postData['file']) ? $postData['file'] : null;

        $isValidBranch = validBranch($branchLeft, $branchRight);

        if ($isValidBranch) {
            // Ensure required parameters are provided
            if ($branchLeft && $branchRight && $file) {
                $filePathInBranchLeft = getFilePathInBranch($branchLeft, $file);
                $fileContent1 = fileContent($branchLeft, $filePathInBranchLeft);

                if ($filePathInBranchLeft) {
                    $fileExistsInBranchRight = getFilePathInBranch($branchRight, $file);

                    if ($fileExistsInBranchRight) {
                        // Generate the diff for the file
                        $diffString = shell_exec("git diff $branchLeft:$filePathInBranchLeft $branchRight:$filePathInBranchLeft");
                        $fileContent2 = fileContent($branchRight, $fileExistsInBranchRight);
                        if ($diffString) { //modified filehere(M)
                            jsonResponse('success', 'Differences', [
                                'content' => [$fileContent1, $fileContent2],
                                'changeType' => 'differences'
                            ]);
                        } else { //same content (U)
                            jsonResponse('success', 'Unchanged', [
                                'content' => [$fileContent1, $fileContent2],
                                'changeType' => 'unchanged'
                            ]);
                        }
                    } else { // deleted on left (D) right side has no file - File exists only in branchLeft, so fetch its content
                        jsonResponse('success', 'Deleted', [
                            'content' => [$fileContent1, "File not found in $branchRight branch."],
                            'changeType' => 'deleted'
                        ]);
                    }
                } else {
                    jsonResponse(
                        'error',
                        'File not found in branchLeft'
                    );
                }
            } else {
                jsonResponse(
                    'error',
                    'Missing parameters'
                );
            }
        } else {
            jsonResponse(
                'error',
                'Branch Not Found'
            );
        }
    }
} elseif (isset($headers['Action']) && $headers['Action'] === 'responseCopy') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Decode POST data
        $postData = json_decode(file_get_contents('php://input'), true);

        // Retrieve required fields from POST data
        $branchLeft = $postData['branchLeft'] ?? null;
        $branchRight = $postData['branchRight'] ?? null;
        $file = $postData['file'] ?? null;
        $editedContent = $postData['editedContent'] ?? null;
        $changeType = $postData['changeType'] ?? null;
        $commitMessage = $postData['commitMessage'] ?? null;

        $isValidBranch = validBranch($branchLeft, $branchRight);

        if ($isValidBranch) {

            // Check if all required data is provided
            if ($branchLeft && $branchRight && $file && $editedContent && $changeType) {

                $fileExistsInLeftBranch = getFilePathInBranch($branchLeft, $file);
                $fileExistsInRightBranch = getFilePathInBranch($branchRight, $file);

                if ($changeType === 'deleted' && $fileExistsInLeftBranch) { //DELETED(D) 

                    // Switch to the right branch to copy the file
                    shell_exec("git checkout $branchRight");

                    $fileHandle = fileHandle($fileExistsInLeftBranch, $editedContent, $commitMessage);

                    if ($fileHandle) {
                        jsonResponse('success', "File copied from $branchLeft to $branchRight successfully.", [
                            'commit_id' => trim(shell_exec("git rev-parse HEAD")),
                            'from_commit_id' => trim(shell_exec("git log -n 1 --pretty=format:%H $branchLeft -- $fileExistsInLeftBranch")),
                            'from_commit_message' => trim(shell_exec("git log -n 1 --pretty=format:%s $branchLeft -- $fileExistsInLeftBranch"))
                        ]);
                    } else {
                        jsonResponse('error', "Failed to copy from $branchLeft to $branchRight.");
                    }
                } elseif ($changeType === 'differences' && $fileExistsInRightBranch || $changeType === 'unchanged' && $fileExistsInRightBranch && $fileExistsInLeftBranch) { //MODIFIED(M)
                    // Switch to the right branch to modify the file
                    shell_exec("git checkout $branchRight");

                    $fileHandle = fileHandle($fileExistsInRightBranch, $editedContent, $commitMessage);

                    if ($fileHandle) {
                        jsonResponse('success', "File modified from $branchRight to $branchRight successfully.", [
                            'commit_id' => trim(shell_exec("git rev-parse HEAD")),
                            'from_commit_id' => trim(shell_exec("git log -n 1 --pretty=format:%H $branchLeft -- $fileExistsInRightBranch")),
                            'from_commit_message' => trim(shell_exec("git log -n 1 --pretty=format:%s $branchLeft -- $fileExistsInRightBranch"))
                        ]);
                    } else {
                        jsonResponse('error', "Failed to write to file $file in $branchRight.");
                    }
                } else {
                    jsonResponse('error', "File $file does not exist in specified branch $branchLeft or $branchRight.");
                }

            } else {
                jsonResponse('error', "Incomplete data provided. Please provide branchLeft, branchRight, file, editedContent, and changeType.");
            }
        }
    }
} elseif (isset($headers['Action']) && $headers['Action'] === 'multipleCopy') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get POST data
        $postData = json_decode(file_get_contents('php://input'), true);
        $branchLeft = $postData['branchLeft'] ?? null;
        $branchRight = $postData['branchRight'] ?? null;
        $files = $postData['checkedFiles'] ?? null;
        $commitMessage = $postData['commitMessage'] ?? null;

        if (validBranch($branchLeft, $branchRight)) {
            $messages = [];
            $from_commit_ids = [];

            // Ensure both branches are up to date
            branchChangeAndPull($branchLeft);
            branchChangeAndPull($branchRight);

            foreach ($files as $file) {
                $fileExistsInLeftBranch = getFilePathInBranch($branchLeft, $file);
                $fileExistsInRightBranch = getFilePathInBranch($branchRight, $file);

                if ($fileExistsInLeftBranch) {
                    // Check and copy files based on modification state
                    if (!$fileExistsInRightBranch) { //(D)
                        shell_exec("git checkout $branchLeft -- $fileExistsInLeftBranch");
                        $messages[] = "File $file copied to $branchRight.";
                        $from_commit_ids[] = trim(shell_exec("git log -n 1 --pretty=format:%H $branchLeft -- $fileExistsInLeftBranch"));

                    } else {
                        $isModified = trim(shell_exec("git diff $branchLeft $branchRight -- $fileExistsInLeftBranch"));
                        if (!empty($isModified)) { //(M)
                            shell_exec("git rm $fileExistsInRightBranch");
                            shell_exec("git checkout $branchLeft -- $fileExistsInLeftBranch");
                            $messages[] = "File $file updated in $branchRight.";
                            $from_commit_ids[] = trim(shell_exec("git log -n 1 --pretty=format:%H $branchLeft -- $fileExistsInLeftBranch"));
                        } else { //(U)
                            $messages[] = "File $file is unchanged in $branchRight. Skipping.";
                            continue;
                        }
                    }
                    shell_exec("git add $fileExistsInLeftBranch");
                } else {
                    $messages[] = "File $file does not exist in $branchLeft.";
                }
            }

            if (stageCommits($commitMessage)) {
                jsonResponse('success', 'Files committed successfully', [
                    'commit_ids' => trim(shell_exec("git rev-parse HEAD")),
                    'messages' => $messages,
                    'from_commit_ids' => isset($from_commit_ids[0]) ? $from_commit_ids[0] : null
                ]);
            } else {
                jsonResponse('error', 'Error during commit/push');
            }
        } else {
            jsonResponse('error', 'Branch validation failed');
        }
    }
} else {
    // print_r($headers);
    print_r(json_encode(['status' => 'error', 'message' => "Headers not matched"]));
}

/*
 * Function to check if the filewrite function is inside  loop
 */
function isFunctionInsideLoop($file, $function)
{
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!$lines)
        return false;

    $insideLoop = false;
    $functionLine = false;

    foreach ($lines as $lineNumber => $line) {
        // Check if the line contains a loop keyword
        if (preg_match('/\b(for|foreach|while|do)\b/', $line)) {
            $insideLoop = true;
        }

        // Check if the line contains the function call
        if (strpos($line, "$function(") !== false && $insideLoop) {
            $functionLine = $lineNumber + 1; // Line numbers are 1-based
            break;
        }
    }
    return $functionLine;
}


/**
 * Function to check if a file has PHP syntax errors.
 * Returns the error message if found, otherwise returns false.
 */
function checkSyntaxErrors($file)
{
    $output = [];
    $resultCode = 0;
    exec("php -l " . escapeshellarg($file), $output, $resultCode);

    if ($resultCode !== 0) {
        // Extract the line number from the output
        $errorMessage = implode("\n", $output);
        return $errorMessage;
    }

    return false;
}

function parseCheck($commitIds, $branch)
{

    // shell_exec("git reset --hard");
    shell_exec("git checkout -f $branch");//Branch Checkout

    $results = [];
    $functionToCheck = 'filewrite';
    $final_result = [];
    foreach ($commitIds as $commitId) {
        // echo json_encode(['status'=>'success','message'=>"hello"]);die;
        // Step 1: Checkout to the specific commit
        shell_exec("git checkout -f $commitId");

        // Step 2: Run Git command to get the list of changed .php files
        $gitCommand = "git diff-tree --no-commit-id --name-only -r $commitId";
        $files = shell_exec($gitCommand);

        if (!$files) {
            echo "No PHP files found in the commit or Git command failed.\n";
            continue;
        }

        // Convert the output into an array of file names
        $fileList = array_filter(explode("\n", trim($files)));

        // Step 3: Check each file for syntax errors and function inside loop
        foreach ($fileList as $file) {
            if (!file_exists($file)) {
                // If the file does not exist, it might have been deleted in the commit
                $results[$commitId][$file] = "File not found.";
                continue;
            }

            // Check for PHP syntax errors
            $syntaxError = checkSyntaxErrors($file);
            if ($syntaxError) {
                preg_match('/Parse error:(.*)/', $syntaxError, $matches);
                $results[$commitId][$file]['syntax_error'] = $matches[0] ?? $syntaxError;
                $results[$commitId][$file]['error'] = true;
            } else {
                $results[$commitId][$file]['syntax_error'] = "No syntax errors.";
            }

            // Check if the function is inside a loop
            $functionLine = isFunctionInsideLoop($file, $functionToCheck);
            if ($functionLine !== false) {
                $results[$commitId][$file]['function_in_loop'] = "Function '$functionToCheck' is inside a loop at line $functionLine.";
                $results[$commitId][$file]['error'] = true;
            } else {
                $results[$commitId][$file]['function_in_loop'] = "Function '$functionToCheck' is not inside a loop.";
            }
            if (empty($results[$commitId][$file]['error'])) {
                unset($results[$commitId][$file]);
            }
        }
        $final_result[$commitId] = $results[$commitId];
    }
    $modified_result = [];
    $status = "Empty";
    foreach ($final_result as $key => $value) {
        if (!empty($value)) {
            $modified_result[$key] = $value;
            $status = "Not empty";
        }
    }
    return ['status' => "$status", 'message' => $modified_result];
}

function afterCherrypickParseCheck($commitIds, $branch)
{
    $results = [];
    $functionToCheck = 'filewrite';
    $final_result = [];
    foreach ($commitIds as $commitId) {
        // echo json_encode(['status'=>'success','message'=>"hello"]);die;
        // Step 1: Checkout to the specific commit
        // shell_exec("git checkout -f $commitId");

        // Step 2: Run Git command to get the list of changed .php files
        $gitCommand = "git diff-tree --no-commit-id --name-only -r $commitId";
        $gitCommand = str_replace("'", "", $gitCommand);
        $files = shell_exec($gitCommand);
        // echo $files;die;
        if (!$files) {
            echo "No PHP files found in the commit or Git command failed.\n";
            continue;
        }

        // Convert the output into an array of file names
        $fileList = array_filter(explode("\n", trim($files)));

        // Step 3: Check each file for syntax errors and function inside loop
        foreach ($fileList as $file) {
            if (!file_exists($file)) {
                // If the file does not exist, it might have been deleted in the commit
                $results[$commitId][$file] = "File not found.";
                continue;
            }
            // Check for PHP syntax errors
            $syntaxError = checkSyntaxErrors($file);
            if ($syntaxError) {
                preg_match('/Parse error:(.*)/', $syntaxError, $matches);
                $results[$commitId][$file]['syntax_error'] = $matches[0] ?? $syntaxError;
                $results[$commitId][$file]['error'] = true;
            } else {
                $results[$commitId][$file]['syntax_error'] = "No syntax errors.";
            }

            // Check if the function is inside a loop
            $functionLine = isFunctionInsideLoop($file, $functionToCheck);
            if ($functionLine !== false) {
                $results[$commitId][$file]['function_in_loop'] = "Function '$functionToCheck' is inside a loop at line $functionLine.";
                $results[$commitId][$file]['error'] = true;
            } else {
                $results[$commitId][$file]['function_in_loop'] = "Function '$functionToCheck' is not inside a loop.";
            }
            if (empty($results[$commitId][$file]['error'])) {
                unset($results[$commitId][$file]);
            }
        }
        $final_result[$commitId] = $results[$commitId];
    }
    $modified_result = [];
    $status = "Empty";
    foreach ($final_result as $key => $value) {
        if (!empty($value)) {
            $modified_result[$key] = $value;
            $status = "Not empty";
        }
    }
    // echo "Modified line 600 die";
    // print_r($modified_result);die;
    return ['status' => "$status", 'message' => $modified_result];
}

function gitHubCheck($headers)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_POST;
        $branch = $postData['branch'];//Branch Name
        if (empty($postData['commitIds'])) {
            echo json_encode(["status" => "error", "message" => "Please Select Commit ID"]);
            die;
        }
        // $path = "var/lib/jenkins/workspace/";
        $path = "C:/xampp/htdocsG/it-check/server2/server";
        // $path = "C:/xampp/htdocs/Git-check/server/server/server";
        // $path = "/home/Staging/jenkin/AgencyDirect";
        $pull = shell_exec("git pull");
        if (strpos($pull, 'fatal:') !== false) {
            echo json_encode(['status' => 'error', 'message' => 'Git Pull error']);
            die;
        }
        $response['status'] = "";
        // $postData['commitIds']=["b273ef47cf18b738a9f01febe230e1b2c3698919"];
        foreach ($postData['commitIds'] as $key => $value) {
            $commit_id = $value;//Commit ID

            // chdir($path);
            $switchBranchCmd = "git checkout -f $branch";
            $switchOutput = shell_exec($switchBranchCmd);
            if (strpos($switchOutput, 'error') !== false) {
                echo json_encode(['status' => 'error', 'message' => 'switch error']);
                die;
                return "Failed to switch to branch $branch: $switchOutput";
            }

            if (!empty($commit_id)) {
                $git = shell_exec("git cherry $branch {$commit_id}");
                if (empty($git)) {
                    echo json_encode(['status' => 'error', 'message' => "Cherry Pick on $branch branch is not executed"]);
                    die;
                }
                $pattern = '/([+-]) ([a-f0-9]{40})/';
                preg_match_all($pattern, $git, $matches);

                $commitIds = [];

                foreach ($matches[1] as $index => $sign) {
                    $commitIds[$sign][] = $matches[2][$index];
                }
                //SUB CONDITIONS
                if (!empty($commitIds['-']) && in_array($commit_id, $commitIds['-'])) {
                    // $commit_id='88090b95274eceef6a3014557d69f24e6161f3a5';
                    $data = getCommitId($commit_id, $branch);
                    if ($data['status'] == 'not available') {
                        $response['status'] = 'error';
                        $response['message'] = "Commit not found in the getCommiId function ";
                    } else {
                        $response['status'] = 'Commit has been cherry-picked';
                        $response['data'][] = $data;
                    }
                }

                //MAIN CONDITION
                elseif (!empty($commitIds['+']) && in_array($commit_id, $commitIds['+'])) {
                    if (!empty($headers['cherry-pick']) && $headers['cherry-pick'] === 'confirmed cherry-pick') {
                        $result = cherrypick($commit_id, $branch);
                        $response['data'][] = $result;
                        $syntaxCheckOutput = afterCherrypickParseCheck([$result['newid']], $branch);
                        if ($syntaxCheckOutput['status'] === "Empty") {
                            // print_r("Remove this in 685 No syntax error");die;
                            shell_exec("git reset --hard HEAD~1");
                            $response['status'] = 'Commit has been cherry-picked';
                        } else {
                            shell_exec("git reset --hard HEAD~1");
                            // print_r("Remove this die in + 689 array");die;
                            print_r(json_encode(['status' => "Syntax Error After Cherry Pick", "response" => $syntaxCheckOutput]));
                            die;
                        }
                    } else {
                        //     // echo json_encode([['status' => 'error', 'message' => 'confirmation for cherry pick']]);die;
                        $response = [
                            'status' => 'confirmation for cherry pick',
                            'message' => 'confirm cherry picking',
                            'commit_id' => $commit_id
                        ];
                        echo json_encode($response);
                        die;
                    }
                }

                //CONDITION 3
                else {
                    $response = [
                        'status' => 'error',
                        'message' => 'Commit not found in either parent or cherry-pick list',
                        'commit_id' => $commit_id,
                        'data' => $commitIds
                    ];
                }

            } else {
                echo json_encode(['status' => 'error', 'message' => 'No commit ID provided']);
                die;
            }
        }
        shell_exec("git push");
        echo json_encode($response);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
        die;
    }
}


// Simulate Merge Content Function
function simulateMergeContent($repoPath, $commitIds, $targetBranch)
{
    // Navigate to the repository
    chdir($repoPath);
    // $commitIds=["a7ff36dfd16e105c010874c3ed7045ad36a72074"];
    foreach ($commitIds as $key => $commitId) {
        // Ensure we're on the target branch
        exec("git checkout $targetBranch 2>&1", $output, $return_var);
        if ($return_var !== 0) {
            return "Error checking out branch: " . implode("\n", $output);
        }

        // Fetch the changes from the commit
        exec("git show $commitId 2>&1", $diffOutput, $return_var);
        if ($return_var !== 0) {
            return "Error fetching commit changes: " . implode("\n", $diffOutput);
        }
        // Return the diff output for visualization
        $diffContent[] = implode("\n", $diffOutput);

        // $filteredDiff = [];
        // foreach (explode("\n", $diffContent) as $line) {
        //     if (substr($line,0,1)!="-") {
        //         // print_r($line);echo "Hello";
        //         $filteredDiff[]=$line;
        //     }
        // }
        // $result=implode("\n",$filteredDiff);
    }
    return $diffContent;
}


function getCommitId($commitId, $branch)
{
    // echo json_encode([['status' => 'error', 'message' => 'enetered into function']]);die;
    // Define the commit ID you want to track across branches
    $commit_id = $commitId;
    // $commit_id = "b273ef47cf18b738a9f01febe230e1b2c3698919";
    // Get the original commit's message and patch (diff)
    $commit_message = trim(shell_exec("git log -1 --format='%s' $commit_id"));
    // echo json_encode([['status' => 'error', 'message' => $commit_message]]);die;
    $commit_patch = trim(shell_exec("git diff $commit_id~1 $commit_id"));
    // echo json_encode([['status' => 'error', 'message' => $commit_patch]]);die;
    $commit_message = str_replace("'", '"', $commit_message);
    // Find candidate commits with the same message in this branch
    $candidate_commits = explode("\n", trim(shell_exec("git log $branch --grep=$commit_message --pretty=format:'%H'")));
    $found = false;
    // echo json_encode([['status' => 'error', 'message' => $candidate_commits]]);die;
    foreach ($candidate_commits as $candidate) {
        $candidate = str_replace("'", '"', $candidate);
        // Get the patch for the candidate commit
        $candidate_patch = trim(shell_exec("git diff $candidate~1 $candidate"));
        // print_r("git diff $candidate~1 $candidate");
        // print_r($commit_patch);
        // print_r($candidate);die;

        if ($commit_patch === $candidate_patch) {
            return [
                'status' => 'Commit has been cherry-picked',
                'message' => 'Successfully Cherry Picked',
                'commit_id' => $commit_id,
                'newid' => $candidate
            ];
            $found = true;
        }
    }
    if (!$found) {
        return [
            'status' => 'not available',
            'message' => 'Commit id not found',

        ];
    }

    // }
}


function cherryPick($commit_id, $branch)
{


    // Perform the cherry-pick operation
    $cherryPickCmd = "git cherry-pick {$commit_id}";
    $cherryPickOutput = shell_exec($cherryPickCmd);

    // Check for any cherry-pick conflicts or failures
    if (strpos($cherryPickOutput, 'CONFLICT') !== false) {
        // Abort the cherry-pick if there's a conflict
        $errorData = shell_exec("git cherry-pick --abort");
        echo json_encode(['status' => 'error', 'message' => 'conflict error', 'error' => $errorData]);
        die;
    }
    // print_r($cherryPickOutput);die;
    // if (strpos($cherryPickOutput, 'error') !== false) {
    //     echo json_encode(['status' => 'error', 'message' => 'cherry pick error']);
    //     die;
    // }
    $errorData = shell_exec("git cherry-pick --continue");
    exec("git diff --cached --quiet || git commit -m 'Auto-committing cherry-picked changes' 2>&1", $output, $returnVar);

    // After cherry-pick, get the new commit ID (which will be the latest commit on the branch)
    $newCommitCmd = "git log -1 --pretty=format:'%H'";
    $newCommitId = shell_exec($newCommitCmd);
    // die;
    // die;Return the new commit ID
    // die;return trim($newCommitId) ? $newCommitId : null;
    return [
        'status' => 'cherry-pick',
        'message' => 'Commit is found in parent branch not cherry-picked ' . $errorData,
        'commit_id' => $commit_id,
        'newid' => $newCommitId
    ];
}

//=====================================================================================================================================================================

function getJenkinData($backLogId, $sprintId)
{
    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => 'http://192.168.1.140/deployment/scrum_tool_api_v1.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => 'sprintId=' . $sprintId . '&backlogId=' . $backLogId,
        CURLOPT_HTTPHEADER => array(
            'Action: query',
            'Content-Type: application/x-www-form-urlencoded'
        ),
    ));

    $response = curl_exec($curl);
    // //-------------------------------------error check message 
    // if (curl_errno($curl)) {
    //     // Handle cURL-specific error
    //     $errorMessage = 'cURL Error: ' . curl_error($curl);
    //     return $errorMessage;
    // }
    // //------------------------------------------
    if (!$response) {
        $response = jenkinDbFetch($sprintId, $backLogId);
    }
    return json_decode($response, 1);
}


// function getallheaders(): array {
//     $headers = [];
//     foreach ($_SERVER as $key => $value) {
//         // Check for HTTP headers
//         if (str_starts_with($key, 'HTTP_')) {
//             $header = str_replace('_', '-', strtolower(substr($key, 5)));
//             $headers[ucwords($header, '-')] = $value;
//         }
//         // Handle Content-Type and Content-Length headers
//         elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
//             $header = str_replace('_', '-', strtolower($key));
//             $headers[ucwords($header, '-')] = $value;
//         }
//     }
//     return $headers;
// }
function branchcreation()
{
    echo shell_exec('git checkout -b dev origin/dev 2>&1');
    echo shell_exec('git checkout -b mh_test origin/mh_test 2>&1');
    echo shell_exec('git checkout -b mh_uat origin/mh_uat 2>&1');
    echo shell_exec('git checkout -b mh_prelive origin/mh_prelive 2>&1');
    echo shell_exec('git checkout -b mh_live origin/mh_live 2>&1');
}



/**
 * Checks the Git branches and Jenkins branches.
 * 
 * @author : Ajmal Akram S
 * @return : array
 */
function checkBranches($data)
{
    //Fetch Git branches
    $gitBranchesRaw = shell_exec("git branch");

    //Explode the raw output into an array of branches
    $gitBranches = explode("\n", $gitBranchesRaw);

    //Filter out empty elements from the array
    $gitBranches = array_filter($gitBranches, fn($branch) => !empty ($branch));

    // Process each branch name
    foreach ($gitBranches as &$branch) {
        // Trim spaces
        $branch = trim($branch);

        // Remove the '*' marker if it's present (for current branch)
        if ($branch[0] === '*') {
            $branch = substr($branch, 2); // Remove "* " (2 characters)
        }
    }

    $Jenkinbranches = !empty($data) ? array_values(array_unique(array_column($data, 'branch_name'))) : [];
    return array_intersect($Jenkinbranches, $gitBranches);
}

/**
 * Validates Git branches and tracks cherry-picked commits.
 * 
 * @param array $data 
 * @return : array - Updated commit data with tracked statuses.
 */
function gitCheck($data)
{
    // $branches = ['dev', 'mh_test', 'mh_uat', 'mh_prelive', 'mh_live'];
    $branches = checkBranches($data);
    $memcache = new Memcache();
    if (!$memcache->connect('localhost', 11211)) {
        die("Could not connect to Memcache");
    }

    $memcache->set('validBranches', $branches, MEMCACHE_COMPRESSED, 2500); // Store for 15min

    // Track cherry-picked commits and add the tracked status
    $updated_data = trackCommitStatuses($data, $branches);
    return $updated_data;
}

/**
 * Loops through commit data and tracks cherry-picked commits based on commit ID and message.
 * 
 * @param array $data - commit data to be tracked.
 * @param array $branches - branches to check for cherry-pick.
 * @return array : updated commit data with tracked statuses.
 */
function trackCommitStatuses($data, $branches)
{
    foreach ($data as &$commit) {
        // $tracked_status = checkCherryPickByContent($commit['commit_id'], $branches);
        $tracked_status = checkCherryPick($commit['commit_id'], $commit['file_name'], $branches);

        // Add tracked status to the commit data
        if (!empty($tracked_status)) {
            $commit['tracked_status'] = $tracked_status;
        }
        $commit['tracked_status'][$commit['branch_name']] = $commit['commit_id'];
    }
    return $data;
}


/**
 * Checks if commit was cherry-picked to any of specified branches.
 * 
 * @param string $commit_id
 * @param string $commit_message
 * @param array $branches - list of branches to check for cherry-picking.
 * @return array associative array including tracked status by branch.
 */
// function checkCherryPick($commit_id, $file_name, $branches)
// {
//     $tracked_status = [];

//     // Loop through the branches to check for the file in the commit history
//     foreach ($branches as $branch) {
//         // Use Git command to check if the file is part of any commit in the branch
//         $result = shell_exec("git log \"$branch\" --format=\"%H\" --name-only --since=\"1 month ago\" -- \"$file_name\"");
//         if (!empty($result)) {
//             $commits = explode("\n", trim($result));
//             // Check if there are any differences by comparing commit diffs between branches
//             $diff_result = shell_exec("git log \"$branch\" --oneline --cherry-pick --right-only $commit_id");
//             // If the diff has changes, it's likely a cherry-pick
//             if (!empty($diff_result)) {
//                 // Add branch name and parent commit ID to tracked_status if cherry-pick detected
//                 $tracked_status[$branch] = $commits[0];
//             }
//         }
//     }
//     return $tracked_status;
// }
function checkCherryPick($commit_id, $file_name, $branches)
{
    $tracked_status = [];

    foreach ($branches as $branch) {
        $result = shell_exec("git log \"$branch\" --format=\"%H\" --name-only --since=\"1 month ago\" -- \"$file_name\"");

        if (!empty($result)) {
            $commits = explode("\n", trim($result));

            // Get the diff (patch) of the original commit
            $commitPatch = trim(shell_exec("git diff {$commit_id}~1 {$commit_id}"));
            $commitsInTargetBranch = explode("\n", trim(shell_exec("git rev-list {$branch}")));

            foreach ($commitsInTargetBranch as $candidate) {
                $candidatePatch = trim(shell_exec("git diff {$candidate}~1 {$candidate}"));

                if ($commitPatch === $candidatePatch) {
                    // Match found, indicating a cherry-pick
                    $tracked_status[$branch] = $commits[0];
                    break;
                }
            }
        }
    }

    return $tracked_status;
}


/**
 * Compares files between two Git branches and tracks their status (added, deleted, modified).
 * @param string $branchLeft
 * @param string $branchRight
 * @param int $postSprintId
 * @param int $postBacklogId
 * @return array - result of comparison with file status and commit dates.
 */
function compareBranches($branch1, $branch2, $postSprintId, $postBacklogId)
{
    // // Get the file list from both branches
    // $filesBranch1 = getFilesInBranch($branch1);
    // $filesBranch2 = getFilesInBranch($branch2);
    //** -------------------------------------------------------------- sprint backlog against
    $filesOnBranch1 = getFilesInBranch($branch1);
    $filesOnBranch2 = getFilesInBranch($branch2);
    $postSprintId = 9;
    $postBacklogId = 10;
    $data = getJenkinData($postBacklogId, $postSprintId);
    foreach ($data as $key => $value) {
        $filenames[] = $data[$key]['file_name'];
    }

    if ((empty($filesOnBranch1)) || (empty($filesOnBranch2))) {
        if (empty($filesOnBranch1)) {
            return ['error' => "$branch1 has no files"];
        } elseif (empty($filesOnBranch2)) {
            return ['error' => "$branch2 has no files"];
        } else {
            return ['error' => "$branch1 and $branch2 has no files"];
        }

    } else {
        // Make filenames unique
        $filenames = array_unique($filenames);

        // Initialize arrays for files present in each branch
        $filesBranch1 = [];
        $filesBranch2 = [];

        // Check and append files to the respective branch arrays
        foreach ($filenames as $file) {
            if (in_array($file, $filesOnBranch1)) {
                $filesBranch1[] = $file;
            }

            if (in_array($file, $filesOnBranch2)) {
                $filesBranch2[] = $file;
            }
        }
        //* *

        if (is_null($filesBranch1) && is_null($filesBranch2)) {
            return $results = [
                'message' => 'No files found in either branch.',
                'file' => 'No Files in both branches',
                'status' => 'error',
                'date_branch1' => '',
                'date_branch2' => '',
            ];

        }

        // Return files for branch1 only
        if (is_null($filesBranch1)) {
            $results = [];
            foreach ($filesBranch2 as $file) {
                $results[] = [
                    'file' => $file,
                    'status' => 'A',
                    'date_branch1' => ''
                    ,
                    'date_branch2' => getFileCommitDate($branch2, $file),
                ];
            }
            return $results;
        }
        // Return files for branch1 only
        if (is_null($filesBranch2)) {
            $results = [];
            foreach ($filesBranch1 as $file) {
                $results[] = [
                    'file' => $file,
                    'status' => 'D',
                    'date_branch1' => getFileCommitDate($branch1, $file),
                    'date_branch2' => ''
                ];
            }
            return $results;
        }

        // Get the diff status between the two branches
        $diffCommand = "git diff --name-status $branch1 $branch2";
        $diffStatus = explode("\n", executeGitCommand($diffCommand));

        $status = [];

        // Merge files from both branches into one list to track all
        $allFiles = array_merge($filesBranch1, $filesBranch2);
        $allFiles = array_unique($allFiles);

        foreach ($allFiles as $file) {
            // Get the status of the file
            $fileStatus = "U"; // Default to Unchanged
            foreach ($diffStatus as $diff) {
                if (strpos($diff, $file) !== false) {
                    $fileStatus = substr($diff, 0, 1); // Get the status (A, D, M)
                    break;
                }
            }

            // Get the commit date of the file in both branches
            $dateBranch1 = in_array($file, $filesBranch1) ? getFileCommitDate($branch1, $file) : '';
            $dateBranch2 = in_array($file, $filesBranch2) ? getFileCommitDate($branch2, $file) : '';

            // Store the result
            $status[] = [
                'file' => $file,
                'status' => $fileStatus,
                'date_branch1' => $dateBranch1,
                'date_branch2' => $dateBranch2
            ];
        }

        return $status;
    }

}

/**
 * Retrieves the list of files in a given Git branch.
 * @param string $branch
 * @return array|null - A list of file names in the branch, or null if no files are found.
 */
function getFilesInBranch($branch)
{
    $command = "git ls-tree -r --name-only $branch";
    $output = executeGitCommand($command);
    return $output ? explode("\n", $output) : null;
}

/**
 * Retrieves the last commit date for a specific file in a given Git branch.
 * @param string $branch
 * @param string $file
 * @return string
 */
function getFileCommitDate($branch, $file)
{
    $command = "git log -1 --pretty=format:\"%ci\" $branch -- \"$file\"";
    return trim(executeGitCommand($command));
}

/**
 * Executes a Git command in the shell and returns the output.
 * @param string $command - Git command to execute
 * @return string output of executed command
 */
function executeGitCommand($command)
{
    $output = [];
    $resultCode = 0;
    exec($command, $output, $resultCode);
    if ($resultCode !== 0) {
        // die("Error executing command: $command");
    }
    return implode("\n", $output);
}

/**
 * Fetches Jenkins-related data from a database based on sprint and backlog IDs.
 * @param int $postSprintId
 * @param int $postBacklogId
 * @return string JSON-encoded result from DB
 */
function jenkinDbFetch($postSprintId, $postBacklogId)
{
    try {
        // // Database credentials
        // $host = "t7roy.h.filess.io";
        // $dbname = "jenkinresponse_snowtwice";
        // $username = "jenkinresponse_snowtwice";
        // $port = "3306";
        // $password = "dd5ab181952955ef120321cc2084d979750c4e1e";

        // Local Database credentials
        $host = 'localhost';
        $dbname = 'jenkin';
        $username = 'root';
        $password = '1234';

        // Create PDO connection
        $conn = new PDO("mysql:host=$host;dbname=$dbname;", $username, $password);

        // Set PDO error mode to exception
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // SQL Query
        $query = "SELECT 
                          jdd.deployment_id,
                          jdd.job_id,
                          jdd.branch_name,
                          jdd.repository_name,
                          jdd.file_name,
                          jdd.file_status,
                          jdd.commit_id,
                          jdd.revision_number,
                          jdd.commit_message,
                          jdd.started_by,
                          jdd.approved_by,
                          jdd.started_datetime,
                          jdd.approved_datetime,
                          td.r_scrum_tool_sprint_id,
                          td.r_scrum_tool_backlog_id
    
                      FROM 
                          jenkinresponse jdd
                      JOIN 
                          trackdeliverytables td
                          ON jdd.job_id = td.r_job_id AND jdd.job_name = td.job_name
                      WHERE 
                          td.r_scrum_tool_sprint_id = :sprintId 
                          AND td.r_scrum_tool_backlog_id = :backlogId";


        // Prepare the SQL query
        $stmt = $conn->prepare($query);

        // Bind the parameters
        $stmt->bindParam(':sprintId', $postSprintId, PDO::PARAM_INT);
        $stmt->bindParam(':backlogId', $postBacklogId, PDO::PARAM_INT);

        // Execute the query
        $stmt->execute();

        // Fetch data
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($data);
    } catch (PDOException $e) {
        // In case of error, show the error message
        echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    }
}

/**
 * Checking branch against git and post data
 * @param string $branchLeft
 * @param string $branchRight
 * @return bool
 */
function validBranch($branchLeft, $branchRight)
{
    global $memcache;
    $validBranches = $memcache->get('validBranches');

    if ($validBranches === false) {
        echo json_encode(['error' => 'error in memcache', 'message' => 'validBranches not found in Memcache!']);
        die;
    }

    // $branch = ['dev' => 'dev', 'test' => 'mh_test', 'uat' => 'mh_uat', 'prelive' => 'mh_prelive', 'live' => 'mh_live'];
    if ((!in_array($branchLeft, $validBranches) && $branchLeft != '') && (!in_array($branchRight, $validBranches) && $branchRight != '')) {
        return false;
    }
    // shell_exec("git checkout $branchLeft");
    // $pull = shell_exec("git pull 2>&1");
    $branchLeft = branchChangeAndPull($branchLeft);
    $branchRight = branchChangeAndPull($branchRight);
    if ((!$branchLeft) && (!$branchRight)) {
        // // Handle git pull failure
        // $response = [
        // 'status' => 'error',
        // 'message' => "Git pull failed. Ensure repository is accessible and up-to-date."
        // ];
        // shell_exec("git checkout $branchRight");
        // $pull = shell_exec("git pull 2>&1");
        // if (!$pull) {
        return false;
        // }
    }
    return true;
}

/**
 * Get full file path in a branch
 * @param mixed $branch
 * @param mixed $file
 * @return string
 */
function getFilePathInBranch($branch, $file)
{
    return trim(shell_exec("git ls-tree -r $branch --name-only | findstr \"$file\""));
}

function fileContent($branch, $filePath)
{
    return shell_exec("git show $branch:$filePath");
}
function stageCommits($commitMessage)
{
    shell_exec("git commit -m \"$commitMessage\"");
    // $push = shell_exec("git push 2>&1");
    // $push = shell_exec("git push");

    exec("git push", $output, $returnCode);

    if ($returnCode === 0) {
        return true; // Push succeeded
    }

    return false; // Push failed
    // print_R($push);
    // die;
    // if (!$push) {
    //     return false;
    // }
    // return true;
    // return strpos($push, 'done') !== false;
}


function branchChangeAndPull($branch)
{
    // Pull the latest changes in both branches
    shell_exec("git checkout $branch");
    // $pull = shell_exec("git pull 2>&1");
    $pull = shell_exec("git pull");
    if (!$pull) {
        return false;
    }
    return true;
}
function fileHandle($file, $editedContent, $commitMessage)
{
    $fileHandle = fopen($file, 'w');
    if ($fileHandle) {
        fwrite($fileHandle, $editedContent);
        fclose($fileHandle);
        shell_exec("git add $file");
        $staged = stageCommits($commitMessage);
        return true;
    }
    return false;
}

function jsonResponse($status, $message, $data = [])
{
    $response = [
        'status' => $status,
        'message' => $message,
    ];

    if (!empty($data)) {
        $response = array_merge($response, $data);
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

?>