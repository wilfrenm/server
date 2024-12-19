<?php
header('Content-Type: application/json');
$headers = getallheaders();
$path = 'C:/xampp/htdocs/Git-check/server2/server';
chdir($path);
// shell_exec('git reset --hard HEAD~1');
// $headers['Action'] = 'check';
// $headers['Action'] = 'check';
// $_SERVER['REQUEST_METHOD'] = 'POST';
// $memcache = new Memcache();
// if (!$memcache->connect('localhost', 11211)) {
//     die("Could not connect to Memcache");
// }
/**
 * --------------------------------------------------------------------------------------------------
 */
// // print_r("hello");die;
// $headers['cherry-pick']='confirm cherry-pick';
// $headers['cherry-pick'] = 'confirmed cherry-pick';
// $_POST['commitIds'][0] = '90352292d2ee95166ded6f8b3b3526d8a30a55f5';
// $_POST['branch'] = 'test';

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
        $filesList = $postData['particularFiles'];
        $branch = $postData['branch'];
        if(!empty($postData['commitIds'])){
            $commitIds = $postData['commitIds'];
            $result  = parseCheck($commitIds,$branch);
        }
        if(!empty($filesList)){
            $result2 = parseFileCheck($filesList,$branch);
            if($result2['status']=="Not empty"){
                if(!empty($result)){
                    $result['status'] = "Not empty";
                    foreach($result2['message'] as $key=>$value){
                        foreach($value as  $file_name=>$syntaxDetails){
                            $result['message'][$key][$file_name] = $syntaxDetails;
                        }
                    }
                    echo json_encode($result);die;
                }
                else{
                    echo json_encode($result2);die;
                }
            }
            else if(empty($result)){
                echo json_encode($result2);die;
            }
        }
        echo json_encode($result);
    }
}
else if(isset($headers['Action']) && $headers['Action'] === "merge"){
    $postData=$_POST;
    $data=simulateMergeContent($path,$postData['commitIds'],$postData['branch'],$postData['particularFiles']);
    echo json_encode($data);
}
 else if (isset($headers['Action']) && $headers['Action'] === 'query') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_POST;
        $postSprintId = $postData['sprintId'];
        $postBacklogId = $postData['backlogId'];
        try {
            // Database credentials
            $host = 'localhost'; // Replace with your database host
            $dbname = 'jenkin'; // Replace with your database name
            $username = 'root'; // Replace with your database username
            $password = ''; // Replace with your database password

            // Create PDO connection
            $conn = new PDO("mysql:host=$host;dbname=$dbname;port=3307", $username, $password);

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

            if (isset($results['message'])) {
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
                            'from_commit_id' => trim(shell_exec("git log -n 1 --pretty=format:%H $branchLeft -- $fileExistsInLeftBranch"))
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
                            'from_commit_id' => trim(shell_exec("git log -n 1 --pretty=format:%H $branchLeft -- $fileExistsInRightBranch"))
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
                    'from_commit_ids' => $from_commit_ids[0]
                ]);
            } else {
                jsonResponse('error', 'Error during commit/push');
            }
        } else {
            jsonResponse('error', 'Branch validation failed');
        }
    }
}
else {
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

function parseCheck($commitIds,$branch){
    
    // shell_exec("git reset --hard");
    shell_exec("git checkout -f $branch");//Branch Checkout

    $results = [];
    $functionToCheck = 'filewrite';
    $final_result = [];
    foreach ($commitIds as $commitId) {
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
        $results = filewiseSyntaxChecking($fileList,$commitId,$functionToCheck);
        
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

function filewiseSyntaxChecking($fileList,$commitId,$functionToCheck){
    // Check each file for syntax errors and function inside loop
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
    return $results;
}

function parseFileCheck($filesList,$branch){
    // shell_exec("git reset --hard");
    shell_exec("git checkout -f $branch");//Branch Checkout

    $results = [];
    $functionToCheck = 'filewrite';
    $final_result = [];
    foreach (json_decode($filesList,true) as $key=>$value) {
        $commitId = $value['commitId'];
        // Step 1: Checkout to the specific commit
        shell_exec("git checkout -f $commitId");

        // Convert the output into an array of file names
        $file_name = $value['file'];
        $results = filewiseSyntaxChecking(array($file_name),$commitId,$functionToCheck);
        
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

function afterCherrypickParseCheck($commitIds,$branch){ 
    $results = [];
    $functionToCheck = 'filewrite';
    $final_result = [];
    foreach ($commitIds as $commitId) {

        // Step 2: Run Git command to get the list of changed .php files
        $gitCommand = "git diff-tree --no-commit-id --name-only -r $commitId";
        $gitCommand = str_replace("'","",$gitCommand);
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
    return ['status' => "$status", 'message' => $modified_result];
}

function FileCherryPick($files, $branch, $headers, $commitMessage = null) {
    // Decode the JSON input
    $data = json_decode($files, true);

    if (!$data || !is_array($data)) {
        return [
            'status' => 'error',
            'message' => 'Invalid JSON input for files.'
        ];
    }
    if (!empty($headers['cherry-pick']) && $headers['cherry-pick'] === 'confirmed cherry-pick') {
        $commitMsgs = json_decode($commitMessage, true);

        // Pull the latest changes and checkout the target branch
        shell_exec("git pull 2>&1");
        shell_exec("git checkout $branch 2>&1");

        $results = [];
        foreach ($data as $key => $value) {
            $commitId = $value['commitId'];
            $file = $value['file'];

            // Check if the file and commit are already cherry-picked
            $logCheckCmd = "git log --pretty=format:'%H' -- $file";
            $logOutput = shell_exec($logCheckCmd);

            if (strpos($logOutput, $commitId) !== false) {
                // If the commit is already cherry-picked, find the cherry-picked commit ID for the specific file
                $blameCmd = "git blame --line-porcelain $file | grep 'commit ' | grep $commitId";
                $blameOutput = shell_exec($blameCmd);
                preg_match('/^commit ([a-f0-9]{40})$/m', $blameOutput, $matches);
                $existingCommitId = $matches[1] ?? null;

                if ($existingCommitId) {
                    $results[] = [
                        'file' => $file,
                        'status' => 'already cherry-picked',
                        'message' => "File $file is already cherry-picked.",
                        'commit_id' => $commitId,
                        'newid' => $existingCommitId,
                    ];
                    continue;
                }
            }

            // Checkout the specific file from the given commit
            $checkoutCmd = "git checkout $commitId -- $file 2>&1";
            $checkoutOutput = shell_exec($checkoutCmd);

            if (strpos($checkoutOutput, 'error') !== false) {
                $results[] = [
                    'file' => $file,
                    'status' => 'error',
                    'message' => "Failed to checkout file $file from commit $commitId."
                ];
                continue;
            }

            // Stage the file
            $addCmd = "git add $file 2>&1";
            $addOutput = shell_exec($addCmd);

            if (strpos($addOutput, 'error') !== false) {
                $results[] = [
                    'file' => $file,
                    'status' => 'error',
                    'message' => "Failed to add file $file to staging area."
                ];
                continue;
            }

            // Determine commit message
            $commitMessage = null;
            foreach ($commitMsgs as $key => $value) {
                foreach ($value as $commitIdKey => $cmtMsgValue) {
                    if ($commitIdKey == $commitId) {
                        $commitMessage = $cmtMsgValue;
                        break;
                    }
                }
                if ($commitMessage) break;
            }

            // Commit the changes
            $commitCmd = "git commit -m \"$commitMessage\" 2>&1";
            $commitOutput = shell_exec($commitCmd);

            if (strpos($commitOutput, 'error') !== false) {
                $results[] = [
                    'file' => $file,
                    'status' => 'error',
                    'message' => "Failed to commit file $file."
                ];
                continue;
            }

            // Get the new commit ID specific to the file
            $newCommitCmd = "git log -1 --pretty=format:'%H' -- $file";
            $newCommitId = trim(shell_exec($newCommitCmd));

            if (!$newCommitId) {
                $results[] = [
                    'file' => $file,
                    'status' => 'error',
                    'message' => "Failed to retrieve new commit ID for file $file."
                ];
                continue;
            }

            $results[] = [
                'file' => $file,
                'status' => 'success',
                'message' => "Successfully cherry-picked file $file.",
                'commit_id' => $commitId,
                'newid' => $newCommitId,
            ];
        }

        // Return the results of the operation
        return [
            'status' => 'Commit has been cherry-picked',
            'message' => 'Cherry-pick operation completed.',
            'data' => $results
        ];
    } else {
        $response = [
            'status' => 'confirmation for cherry pick',
            'message' => 'Confirm cherry picking',
            'commit_id' => 'Id check'
        ];
        echo json_encode($response);
        die;
    }
}
 

function gitHubCheck($headers)
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postData = $_POST;
        $branch = $postData['branch'];//Branch Name
        // $path = "var/lib/jenkins/workspace/";
        // $path = "C:/xampp/htdocsG/it-check/server2/server";
        // $path = "C:/xampp/htdocs/Git-check/server/server/server";
        // $path = "/home/Staging/jenkin/AgencyDirect";
        $pull = shell_exec("git pull");
        if (strpos($pull, 'fatal:') !== false) {
            echo json_encode(['status' => 'error', 'message' => 'Git Pull error']);
            die;
        }
        $response['status'] = "";
        if (empty($postData['commitIds']) || $postData['particularFiles']!='[]') {print_r(!empty($postData['particularFiles']["key"]));var_dump($postData['particularFiles']);
            $returnValue = FileCherryPick($postData['particularFiles'],$branch,$headers,!empty($postData['fileCommitMsg'])?$postData['fileCommitMsg']:null);
            if($returnValue['status']=="Commit has been cherry-picked"){
                if (empty($postData['commitIds'])){
                    echo json_encode($returnValue);
                    die;
                }
                else{
                    foreach($returnValue['data'] as $key=>$value)
                    $response['data'][]=$value;
                }
            }
            else{
                echo json_encode(["status" => "error", "message" => "File Commit exist"]);
                die;
            }
        }
        if(!empty($postData['message']))
            $commitMsgArray=explode(",",$postData['message']);
        foreach ($postData['commitIds'] as $key => $value) {
            $commit_id = $value;//Commit ID
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
                // print_r($commitIds);die;
                //SUB CONDITIONS
                if (!empty($commitIds['-']) && in_array($commit_id, $commitIds['-'])) {
                    $data = getCommitId($commit_id, $branch);
                    if(!empty($commitMsgArray))
                        array_shift($commitMsgArray);
                    if ($data['status'] == 'not available') {
                        $response['status'] = 'error';$response['message']="Commit not found in the getCommiId function ";
                    } else {
                        $response['status'] = 'Commit has been cherry-picked';
                        $response['data'][] = $data;
                    }
                }

                //MAIN CONDITION
                elseif (!empty($commitIds['+']) && in_array($commit_id, $commitIds['+'])) {
                    if (!empty($headers['cherry-pick']) && $headers['cherry-pick'] === 'confirmed cherry-pick') {
                        if(empty($postData['message'])){
                            $result = cherrypick($commit_id, $branch);
                        }
                        else{
                            $commitMessage=$commitMsgArray[0];
                            array_shift($commitMsgArray);
                            $result = cherryPickWithMessage($commit_id, $branch, $commitMessage);
                        }
                        $response['data'][] =$result;
                        $syntaxCheckOutput = afterCherrypickParseCheck([$result['newid']],$branch);
                        if($syntaxCheckOutput['status'] === "Empty"){
                            // shell_exec("git reset --hard HEAD~1");
                            $response['status'] = 'Commit has been cherry-picked';
                        }
                        else{
                            shell_exec("git reset --hard HEAD~1");
                            print_r(json_encode(['status'=>"Syntax Error After Cherry Pick","response"=>$syntaxCheckOutput]));die;
                        }
                    } else {
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
        // shell_exec("git push");
        echo json_encode($response);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
        die;
    }
}

// Simulate Merge Content Function
function simulateMergeContent($repoPath, $commitIds, $targetBranch, $particularFiles) {
    // Navigate to the repository
    chdir($repoPath);
    // foreach($commitIds as $key=>$commitId){
    //     // Ensure we're on the target branch
    //     exec("git checkout $targetBranch 2>&1", $output, $return_var);
    //     if ($return_var !== 0) {
    //         return "Error checking out branch: " . implode("\n", $output);
    //     }

    //     // Fetch the changes from the commit
    //     exec("git show $commitId 2>&1", $diffOutput, $return_var);
    //     if ($return_var !== 0) {
    //         return "Error fetching commit changes: " . implode("\n", $diffOutput);
    //     }
    //     // Return the diff output for visualization
    //     $diffContent[] = implode("\n", $diffOutput);
    // }
    // return $diffContent;


    $input['commit'] = $commitIds;
    $input['particularFiles'] = $particularFiles;
    $input['targetBranch'] = $targetBranch;
    // Handle the `commit` field (existing functionality)
    if (!empty($input['commit'])) {
        $commitIds = $input['commit'];
        if (is_array($commitIds)) {
            foreach ($commitIds as $commitId) {
                // Ensure we're on the target branch
                exec("git checkout " . escapeshellarg($input['targetBranch']) . " 2>&1", $output, $return_var);
                if ($return_var !== 0) {
                    return "Error checking out branch: " . implode("\n", $output);
                }

                // Fetch the changes from the commit
                exec("git show " . escapeshellarg($commitId) . " 2>&1", $diffOutput, $return_var);
                if ($return_var !== 0) {
                    return "Error fetching commit changes: " . implode("\n", $diffOutput);
                }

                // Store the diff output for visualization
                $diffContent[] = implode("\n", $diffOutput);
            }
        }
    }

    // Handle the `particularFiles` field (new functionality)
    if (!empty($input['particularFiles'])) {
        $fileCommits = json_decode($input['particularFiles'][0], true);

        if (is_array($fileCommits)) {
            foreach ($fileCommits as $fileCommit) {
                if (isset($fileCommit['commitId']) && isset($fileCommit['file'])) {
                    $commitId = $fileCommit['commitId'];
                    $file = $fileCommit['file'];

                    // Ensure we're on the target branch
                    exec("git checkout " . escapeshellarg($input['targetBranch']) . " 2>&1", $output, $return_var);
                    if ($return_var !== 0) {
                        return "Error checking out branch: " . implode("\n", $output);
                    }

                    // Fetch the specific file changes from the commit
                    exec("git show " . escapeshellarg($commitId) . " -- " . escapeshellarg($file) . " 2>&1", $diffOutput, $return_var);
                    if ($return_var !== 0) {
                        return "Error fetching file changes: " . implode("\n", $diffOutput);
                    }

                    // Store the diff output for visualization
                    $diffContent[] = implode("\n", $diffOutput);
                }
            }
        }
    }

    // Return the combined diff content
    return $diffContent;



}

function getCommitId($commitId, $branch) {
    // Get the diff (patch) of the original commit
    $commitPatch = trim(shell_exec("git diff {$commitId}~1 {$commitId}"));

    // Retrieve all commits from the target branch
    $commitsInTargetBranch = explode("\n", trim(shell_exec("git rev-list {$branch}")));

    foreach ($commitsInTargetBranch as $candidate) {
        // Get the diff (patch) of each candidate commit in the target branch
        $candidatePatch = trim(shell_exec("git diff {$candidate}~1 {$candidate}"));

        // Compare the patch of the original commit with the candidate commit
        if ($commitPatch === $candidatePatch) {
            // echo $commitPatch." ".$candidatePatch;die;
            return [
                'status' => 'Commit has been cherry-picked',
                'message' => 'Successfully Cherry Picked',
                'commit_id' => $commitId,
                'newid' => $candidate
            ];
        }
    }

    return [
        'status' => 'no_match',
        'message' => 'No matching commit found in target branch',
    ];
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
    $errorData = shell_exec("git cherry-pick --continue");
    exec("git diff --cached --quiet || git commit -m 'Auto-committing cherry-picked changes' 2>&1", $output, $returnVar);

    // After cherry-pick, get the new commit ID (which will be the latest commit on the branch)
    $newCommitCmd = "git log -1 --pretty=format:'%H'";
    $newCommitId = shell_exec($newCommitCmd);
    return [
        'status' => 'cherry-pick',
        'message' => 'Commit is found in parent branch not cherry-picked ' . $errorData,
        'commit_id' => $commit_id,
        'newid' => $newCommitId
    ];
}

function cherryPickWithMessage($commit_id, $branch, $customMessage = null)
{
    // Perform the cherry-pick operation
    $cherryPickCmd = "git cherry-pick {$commit_id} --no-commit 2>&1";
    $cherryPickOutput = shell_exec($cherryPickCmd);

    // Check for conflicts or errors during cherry-pick
    if (strpos($cherryPickOutput, 'CONFLICT') !== false) {
        // Abort the cherry-pick if there's a conflict
        $abortOutput = shell_exec("git cherry-pick --abort 2>&1");
        return [
            'status' => 'error',
            'message' => 'Conflict encountered during cherry-pick.'
        ];
    } 

    // Commit the changes with the custom or default commit message
    $commitMessage = $customMessage ?: 'Auto-committing cherry-picked changes';
    $commitCmd = "git commit -m \"$commitMessage\" 2>&1";
    $commitOutput = shell_exec($commitCmd);

    // Retrieve the new commit ID
    $newCommitCmd = "git log -1 --pretty=format:'%H'";
    $newCommitId = trim(shell_exec($newCommitCmd));

    if (!$newCommitId) {
        return [
            'status' => 'error',
            'message' => 'Failed to retrieve the new commit ID.',
            'details' => 'Commit might not have been created successfully.'
        ];
    }

    // Return success response
    return [
        'status' => 'success',
        'message' => 'Cherry-pick completed successfully.',
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
    // $memcache = new Memcache();
    // if (!$memcache->connect('localhost', 11211)) {
    //     die("Could not connect to Memcache");
    // }

    // $memcache->set('validBranches', $branches, MEMCACHE_COMPRESSED, 900); // Store for 15min

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
            'message' => 'both branches null',
            'file' => 'No Files in both branches',
            'status' => 'Not Found in both branches',
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

        // Database credentials
        $host = 'localhost';
        $dbname = 'jenkin';
        $username = 'root';
        $password = '';

        // $host = "t7roy.h.filess.io";
        // $dbname = "jenkinresponse_snowtwice";
        // $username = "jenkinresponse_snowtwice";
        // $port = "3306";
        // $password = "dd5ab181952955ef120321cc2084d979750c4e1e";

        // Create PDO connection
        $conn = new PDO("mysql:host=$host;dbname=$dbname;port=3307;", $username, $password);

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
    // global $memcache;
    // $validBranches = $memcache->get('validBranches');

    // if ($validBranches === false) {
    //     echo "Error: validBranches not found in Memcache!";
    //     die;
    // }

    // // $branch = ['dev' => 'dev', 'test' => 'mh_test', 'uat' => 'mh_uat', 'prelive' => 'mh_prelive', 'live' => 'mh_live'];
    // if ((!in_array($branchLeft, $validBranches) && $branchLeft != '') && (!in_array($branchRight, $validBranches) && $branchRight != '')) {
    //     return false;
    // }
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
