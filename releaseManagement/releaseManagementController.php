<?php
namespace App\Controllers;

/**
 * ReleaseManagementController.php
 *
 * @category   Controller
 * @purpose    manages commits done for the products and perform movement of files from one branch to another
 * @author     Ajmal Akram,Karthick Raja,Deepika,Wilfren
 * @created    28 OCTOBER 2024
 */
use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use App\Models\SprintModel;
use CodeIgniter\HTTP\Response;
use DateTime;
use Config\SprintModelConfig;
use App\Services\EmailService;
use App\Services\otpGenrator;
use App\Services\NotesService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Config\Services;
use App\Helpers\CustomHelpers;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpParser\Node\Expr\Print_;

class ReleaseManagementController extends BaseController
{
    protected $backlogModel;
    protected $releaseManagementModel;
    protected $logdownloadHistory;
    protected $branchurlTracker;
    protected $otp;

    public function __construct()
    {
        $this->releaseManagementModel = model(\App\Models\ReleaseManagementModel::class);
        $this->logdownloadHistory = model(\App\Models\logdownloadHistory::class);
        $this->branchurlTracker = model(\App\Models\branchurlTracker::class);
        $this->otp = new otpGenrator;
    }

    /**
     * Commit Group for cherry picking required commit ids
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Wilfren M
     * @created     01 November 2024
     * @purpose     Commit Details Group for cherry pick
     * @return object or string
     */
    function commitGroup($progstatus): object|string
    {
        // print_r($_SESSION);die;
        // Memcache data access
        $cache = Services::cache();
        $cachedValue = $cache->get('groupedJenkinResponse');
        // print_r($cachedValue);
        // die;
        $commit_details = array();
        //Getting role id of the to restrict the cherry pick operation
        $userservice = service("users");
        $roleId = $userservice->getUserRoleId(session()->get('employee_id'));
        $roleId = $roleId[0]['id'];
        $projectId = session()->get('selected_product_id');

        // $projectId = 16;
        // Progress indication variables for view page
        if ($progstatus == 1) {
            // environment type for getting branch name of next linear branch
            $environment_type = "test";
            $from_environment_type = "dev";
            $commit_id_position = 1;
            //Fetching data for target branch
            $branches = $this->releaseManagementModel->branchFetching($projectId, $environment_type);
            $fromBranch = $this->releaseManagementModel->branchFetching($projectId, $from_environment_type);
            $dev = 1;
            $test = 0;
            $uat = 0;
            $prelive = 0;
            $live = 0;
            $progress = "test";
        } else if ($progstatus == 2) {
            $environment_type = "uat";
            $from_environment_type = "test";
            $commit_id_position = 2;
            $branches = $this->releaseManagementModel->branchFetching($projectId, $environment_type);
            $fromBranch = $this->releaseManagementModel->branchFetching($projectId, $from_environment_type);
            $dev = 1;
            $test = 1;
            $uat = 0;
            $prelive = 0;
            $live = 0;
            $progress = "uat";
        } else if ($progstatus == 3) {
            $environment_type = "prelive";
            $from_environment_type = "uat";
            $commit_id_position = 3;
            $branches = $this->releaseManagementModel->branchFetching($projectId, $environment_type);
            $fromBranch = $this->releaseManagementModel->branchFetching($projectId, $from_environment_type);
            $dev = 1;
            $test = 1;
            $uat = 1;
            $prelive = 0;
            $live = 0;
            $progress = "prelive";
        } else if ($progstatus == 4) {
            $environment_type = "live";
            $from_environment_type = "prelive";
            $commit_id_position = 4;
            $branches = $this->releaseManagementModel->branchFetching($projectId, $environment_type);
            $fromBranch = $this->releaseManagementModel->branchFetching($projectId, $from_environment_type);
            $dev = 1;
            $test = 1;
            $uat = 1;
            $prelive = 1;
            $live = 0;
            $progress = "live";
        } else {
            return redirect()->route('release-management/commitstatus');
        }

        //Fetch the authority for the user
        $permissionFetch = $this->releaseManagementModel->branchFetching($projectId, $environment_type);
        // Checking if memcache has data or not if not redirect to commitstatus page 
        if (empty($cachedValue[$progress]) or empty($permissionFetch[0]['authority'])) {
            // print_r($cachedValue);
            // print_r($permissionFetch);
            // die;
            return redirect()->route('release-management/commitstatus');
        }

        //Seperate the role ids from string
        $permissionString = explode(",", $permissionFetch[0]['authority']);
        //to convert all string values to integer
        $permissions = array_map('intval', $permissionString);
        $target_branch = explode("/", $branches[0]['git_url'])[count(explode("/", $branches[0]['git_url'])) - 1];
        $fromBranch = explode("/", $fromBranch[0]['git_url'])[count(explode("/", $fromBranch[0]['git_url'])) - 1];

        // Modifying data and segregating file names for easy access in view page
        foreach ($cachedValue[$progress] as $key => $value) {
            $count = 1;
            $current_commit_id = '';
            foreach ($value['tracked_status'] as $key2 => $value2) {
                if ($commit_id_position == $count) {
                    $current_commit_id = $value2;
                    break;
                }
                $count++;
            }
            $explode = explode('/', $value['file_name']);
            if (array_key_exists($current_commit_id, $commit_details)) {
                $commit_details[$current_commit_id]['files'][] = $explode[count($explode) - 1];
            } else {
                $dateObject = new DateTime($value['started_datetime']);
                // Format the date as "06 Dec 2021 08:19:08"
                $formattedDate = $dateObject->format('d M  H:i A');
                $commit_details[$current_commit_id][0] = array(
                    'author' => $value['started_by'],
                    'repository_name' => $value['repository_name'],
                    'branch_name' => $value['branch_name'],
                    'files' => $explode[count($explode) - 1],
                    'date' => $formattedDate,
                    'commit_message' => $value["commit_message"]
                );
                $commit_details[$current_commit_id]['files'] = array($explode[count($explode) - 1]);
            }
            $backlog_id = session()->get("selected_backlog_id");
            $sprint_id = session()->get("selected_sprint_id");
            // $backlog_id = $value['r_scrum_tool_backlog_id'];
            // $sprint_id = $value['r_scrum_tool_sprint_id'];
        }
        //For rearranging the files based on the length shortest first for better view
        foreach ($commit_details as &$commit) {
            if (isset($commit['files']) && is_array($commit['files'])) {
                usort($commit['files'], function ($a, $b) {
                    return strlen($a) <=> strlen($b);
                });
            }
        }
        //Data for view page
        $data = [
            'commit_details' => $commit_details,
            'progresscontent' => [$dev, $test, $uat, $prelive, $live, $target_branch],
            'backlog_id' => $backlog_id,
            'from_branch' => $fromBranch,
            'sprint_id' => $sprint_id,
            'permission' => $permissions,
            'roleId' => $roleId
        ];
        return $this->template_view('releaseManagement/commitGroup', $data, 'Group Details', ['File Tracking ' => ASSERT_PATH . 'release-management/commitstatus', 'Specific File' => '#']);
    }

    /**
     * Product, Backlog, Sprint details for Jenkins Software API request Handling
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Wilfren M
     * @created     07 November 2024
     * @purpose     Handling Api request
     * @return object or string
     */
    public function apiRequestHandle($productName)
    {
        // header('Content-Type: application/json');
        $headers = getallheaders();
        echo "<pre>";
        $headers['Action'] = "jenkinsRequest";
        if (!empty($headers['Action']) && $headers['Action'] == "jenkinsRequest") {
            $product_id = $this->releaseManagementModel->productIdFetch($productName);
            $sprint_details = $this->releaseManagementModel->getProductsSpirints($product_id[0]['product_id']);
            foreach ($sprint_details as $key => $value) {
                $backlog[] = $this->releaseManagementModel->getProductsBacklogs($value["sprint_id"]);
                $sprint_details[$key]['backlog'] = $this->releaseManagementModel->getProductsBacklogs($value["sprint_id"]);
            }
        } else {
            echo "Headers not available $productName";
        }
    }

    /**
     * Resource allocation if free allow modification else do not allow modification
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Wilfren
     * @created     05 November 2024
     * @purpose     Updating status
     * @return object or string
     */
    public function statusUpdate()
    {
        $message = $this->request->getPost();
        $projectId = session()->get('selected_product_id');
        $check = $this->releaseManagementModel->existenceOfProjectCheck($projectId);
        $result = [];
        if ($message['status'] == 'Occupied') {
            if (!empty($check)) {
                if ($check[0]['status'] == "Occupied") {
                    return json_encode(['status' => "Error", 'message' => "Resource is Occupied from controller"]);
                    die;
                }
                $result = $this->releaseManagementModel->updateStatus($message, $projectId);
            } else {
                $result = $this->releaseManagementModel->insertStatus($message, $projectId);
            }
            if ($result) {
                return json_encode(['status' => 'success', 'message' => "$result"]);
            } else {
                return json_encode(['status' => 'error', 'message' => 'Not inserted']);
            }
        } else {
            $result = $this->releaseManagementModel->updateStatus($message, $projectId);
            if ($result) {
                return json_encode(['status' => 'success', 'message' => "$result"]);
            }
        }
    }

    /**
     * Clears the memcache Data after cherry picking
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Wilfren
     * @created     05 November 2024
     * @purpose     Memcache clear
     * @return string
     */
    public function memcacheClear()
    {
        $cache = Services::cache();
        $cache->delete('groupedJenkinResponse');
        echo json_encode(["status" => "success"]);
    }



    /**
     * Handles commit status display for release management.
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Ajmal Akram S
     * @created     28 October 2024
     * @purpose     Fetches branches related to a project or defaults to predefined branches, then renders the commit status view with appropriate data.
     * @return string Rendered HTML for the commit status view.
     */
    public function commitStatus()
    {
        return $this->template_view('releaseManagement/commitStatus', null, 'Commit Cruise Control', ['Home' => ASSERT_PATH . 'dashboard/dashboardView', 'File Tracking' => ASSERT_PATH . 'release-management/commitstatus']);
    }

    /**
     * Retrieves the products/sprints/backlogs details for a given ID.
     * 
     * @author      Ajmal Akram S
     * @created     02 November 2024
     * @param int   $parentId The ID of the products/sprints/backlogs to fetch relative sprint/backlogs.
     * @return \CodeIgniter\HTTP\Response JSON response containing the sprint's backlog's details.
     */
    public function getData()
    {
        $type = $this->request->getGet('type');
        $parentId = $this->request->getGet('parent_id');

        $data = [];
        switch ($type) {
            case 'product':
                $data = $this->releaseManagementModel->getUsersProductDetails(session('employee_id'));
                break;
            case 'sprint':
                if ($parentId) {
                    $data = $this->releaseManagementModel->getProductsSpirints($parentId);
                }
                break;
            case 'backlog':
                if ($parentId) {
                    $data = $this->releaseManagementModel->getProductsBacklogs($parentId);
                }
                break;
        }

        // Transform data to a consistent format
        $response = array_map(function ($item) {
            return [
                'id' => $item['id'],
                'name' => $item['name'],
            ];
        }, $data);

        return $this->response->setJSON($response);
    }

    /**
     * Stores the selected product, sprint, and backlog details into the session for handling commits.
     * 
     * @author      Ajmal Akram S
     * @created     05 November 2024
     * @return void Outputs a JSON response indicating success or failure.
     */
    public function trackDetailSession()
    {
        // Retrieve data sent via the AJAX POST request
        $data = $this->request->getPost([
            'product_id',
            'sprint_id',
            'backlog_id',
        ]);

        // Store the data in session
        session()->set('selected_product_id', $data['product_id']);
        session()->set('selected_sprint_id', $data['sprint_id']);
        session()->set('selected_backlog_id', $data['backlog_id']);

        // Respond with a success message in JSON format
        echo json_encode(['status' => 'success', 'message' => 'Session data stored successfully.']);
    }

    /**
     * Retrieves the selected product, sprint, and backlog details from the session.
     * 
     * @author      Ajmal Akram S
     * @created     06 November 2024
     * @return void Outputs a JSON response containing the session data or an error message.
     */
    public function getSessionData()
    {
        // Retrieve session data
        $session_data = [
            'selected_product_id' => session()->get('selected_product_id'),
            'selected_sprint_id' => session()->get('selected_sprint_id'),
            'selected_backlog_id' => session()->get('selected_backlog_id'),
        ];

        if ($session_data['selected_product_id']) {
            echo json_encode([
                'status' => 'success',
                'data' => $session_data
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Session data not found.']);
        }
    }

    /**
     * Clears the selected product, sprint, and backlog details from the session.
     * 
     * @author      Ajmal Akram S
     * @created     11 November 2024
     * @return \CodeIgniter\HTTP\Response JSON response indicating the success of session destruction.
     */
    public function destroySession()
    {
        // Destroy session variables
        session()->remove([
            'selected_product_id',
            'selected_product_name',
            'selected_sprint_id',
            'selected_sprint_name',
            'selected_backlog_id',
            'selected_backlog_name',
        ]);

        // Respond with success
        return $this->response->setJSON(['success' => true]);
    }

    /**
     * Sends a cURL request to fetch Jenkins-related data based on sprint and backlog.
     * Groups and processes the Jenkins response into categories for tracking.
     *
     * @author          Ajmal Akram S
     * @param int       $sprint - The sprint ID for the request. | $backlog - The backlog ID for the request.
     * @return JSON -   Response containing grouped Jenkins data or error message.
     */
    public function jenkinRequest($sprint, $backlog)
    {
        helper('curl_helper');

        // Prepare the POST data
        $postData = [
            'sprintId' => 9,
            'backlogId' => 10
        ];

        // Headers for the cURL request
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Action: query'
        ];

        // Make the cURL request using the helper function
        $response = makeCURLRequest("http://localhost/Git-check/server/test.php", $postData, $headers);

        // Handle errors
        if (isset($response['error'])) {
            return $this->response->setJSON(['status' => 'error', 'message' => $response['error']]);
        }

        // Decode the response if needed
        $groupedJenkinResponse = $this->groupByCommits($response);

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $groupedJenkinResponse
        ]);
    }

    /**
     * Fetch branches from environment setup DB
     *
     * @author          Ajmal Akram S
     * @return JSON -   Response containing branches
     */
    public function fileGetBranches()
    {
        $projectId = session()->get('selected_product_id');
        $branchesValues = [];
        if ($projectId) {
            $branches = $this->releaseManagementModel->branchFetchAll($projectId);
            foreach ($branches as $key => $value) {
                $branchesValues[] = explode("/", $branches[$key]['git_url'])[count(explode("/", $branches[$key]['git_url'])) - 1];
            }
        } else {
            $branchesValues = ["error"];
        }
        // return $branchesValues;
        return $this->response->setJSON($branchesValues);
    }

    /**
     * Groups the Jenkins response data by tracked commit statuses across environments.
     *
     * @author          Ajmal Akram S
     * @param string    $jenkinResponse - JSON response from Jenkins.
     * @return array -  Contains grouped commit data and their statistics.
     */
    public function groupByCommits($jenkinResponse)
    {
        $jenkinResponseArray = json_decode($jenkinResponse, true);

        $branchesResponse = $this->fileGetBranches()->getBody();
        $branches = json_decode($branchesResponse, true);

        $branchNames = ['dev', 'test', 'uat', 'prelive', 'live'];
        $branch = [];

        // Dynamically map branches
        foreach ($branchNames as $index => $branchName) {
            $branch[$branchName] = isset($branches[$index]['git_url']) ? $branches[$index]['git_url'] : $branches[$index];
        }

        $total = [];
        $merge = [];
        // Iterate through Jenkins response array
        foreach ($jenkinResponseArray as $value) {
            foreach ($branch as $currentBranch => $currentBranchUrl) {
                if ($value['branch_name'] == $branch[$currentBranch]) {
                    // Find the index of the current branch in $branchNames
                    $currentIndex = array_search($currentBranch, $branchNames);
                    $nextBranch = $branchNames[$currentIndex + 1] ?? null; // Get the next branch if it exists

                    // Check if tracked status for the next branch is empty
                    if ($nextBranch && empty($value['tracked_status'][$branch[$nextBranch]])) {
                        $merge[$nextBranch][] = $value; // Use an array to store multiple values
                    }

                    // Update total count for the current branch
                    $total[$currentBranch] = ($total[$currentBranch] ?? 0) + 1;
                    break; // Exit the inner loop once matched
                }
            }
        }

        // Call and Store a value in cache
        $cache = Services::cache();
        $cache->save('groupedJenkinResponse', $merge, 9000);  // 900 seconds (15 minutes)

        $data = [$merge, $total];
        return $data;
    }

    /**
     * Sends a cURL request to fetch file modification data between two branches.
     *
     * @author          Ajmal Akram S
     * @param string    $branchLeft - Name of the first branch. & $branchRight - Name of the second branch.
     * @return JSON -   Response containing file modification data or error message.
     */
    public function fileRequest($branchLeft, $branchRight)
    {
        helper('curl_helper');

        // Prepare the POST data
        $postData = [
            'branchLeft' => $branchLeft,
            'branchRight' => $branchRight,
            'sprintId' => 3,
            'backlogId' => 1
        ];

        // Headers for the cURL request
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Action: file'
        ];

        // Make the cURL request using the helper function
        $response = makeCURLRequest("http://localhost/Git-check/server/test.php", $postData, $headers);

        // Handle errors
        if (isset($response['error'])) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => $response['error']
            ]);
        }

        // Process the response
        $data = $this->fileModifications($response);

        // Prepare the response
        $responsedFileBranches = [
            'status' => $data === 'error' ? 'error' : 'success',
            'data' => $data === 'error' ? 'No file data found in branches or invalid response format.' : $data
        ];

        return $this->response->setJSON($responsedFileBranches);
    }

    /**
     * Processes and separates file modification data by branches.
     *
     * - Decodes the JSON response and ensures it is valid.
     * - Formats file modification dates for readability.
     * - Categorizes files as unique to branch 1, branch 2, or common to both.
     * - Returns a JSON-encoded response containing categorized file data.
     *
     * @author          Ajmal Akram S
     * @param string    $response - JSON response from the server.
     * @return string - JSON-encoded data for file modifications or an error message.
     */
    public function fileModifications($response)
    {
        // return $jsonOutput;
        $results = json_decode($response, true);

        // Check if decoding was successful
        if ($results === null) {
            return 'error';
        }

        // Initialize arrays for branch-specific files
        $data_1 = [];
        $data_2 = [];

        // Separate the files based on branch-specific changes
        foreach ($results as &$result) {
            // Format the dates
            if (!empty($result['date_branch1'])) {
                // print_r($result['date_branch1']);
                // exit();
                $result['date_branch1'] = CustomHelpers::formatDateTime($result['date_branch1']); // formatGitDate($result['date_branch1']);
            }
            if (!empty($result['date_branch2'])) {
                $result['date_branch2'] = CustomHelpers::formatDateTime($result['date_branch2']);
                ;
            }

            // Files that are only in branch 1
            if (!empty($result['date_branch1']) && empty($result['date_branch2'])) {
                $data_1[] = $result; // Files added/modified in branch 1
            }
            // Files that are only in branch 2
            elseif (!empty($result['date_branch2']) && empty($result['date_branch1'])) {
                $data_2[] = $result; // Files added/modified in branch 2
            }
            // Files modified in both branches
            elseif (!empty($result['date_branch1']) && !empty($result['date_branch2'])) {
                $data_1[] = $result; // Files with changes in branch 1
                $data_2[] = $result; // Files with changes in branch 2
            }
        }

        // Prepare the response
        $response = [
            'status' => 'success',
            'data_1' => $data_1,  // Files for branch 1
            'data_2' => $data_2   // Files for branch 2
        ];

        $jsonOutput = json_encode($response);

        return $jsonOutput;
    }

    /**
     * Verifies if the current user has authority to perform cherry-pick operations on a branch.
     *
     * @author          Ajmal Akram S
     * @param string    $to_environment - The target environment/branch.
     * @return JSON -   Response containing the user's role ID and allowed permissions for the environment.
     */
    public function branchAuthority($to_environment)
    {
        //Getting role id of the to restrict the cherry pick operation
        $userservice = service("users");
        $roleId = $userservice->getUserRoleId(session()->get('employee_id'));
        $roleId = $roleId[0]['id'];
        $projectId = session()->get('selected_product_id');

        //Fetch the authority for the user
        $permissionFetch = $this->releaseManagementModel->branchFetching($projectId, $to_environment);

        //Seperate the role ids from string
        $permissionString = explode(",", $permissionFetch[0]['authority']);

        //to convert all string values to integer
        $permissions = array_map('intval', $permissionString);

        return $this->response->setJSON([
            'roleId' => $roleId,
            'permissions' => $permissions
        ]);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     fetching environment setup 
     * @return object | string
     */
    function environmentSetup()
    {
        $userservice = service("users");
        $getuserproject = $userservice->getuserProjects();
        session()->set("getuserproject", $getuserproject);
        $data = $this->releaseManagementModel->environmentsetup($getuserproject);
        return $this->template_view('releaseManagement/environmentSetup', $data, 'Environment Setup', ['Home' => ASSERT_PATH . 'dashboard/dashboardView', 'Environment Setup' => ASSERT_PATH . 'release-management/environmentSetup']);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     Function to view and manage specific environment details based on ID 
     * @return object
     */
    function environmentView($id, $action)
    {
        $userservice = service("users");
        $usersbyroleid = $userservice->userRoles();
        $usersbyproject = $userservice->userProjects($id);
        // Retrieve data for the environment with the given ID
        $data = $this->releaseManagementModel->environmentview($id);
        // If there’s no existing product ID, insert a new environment for the given ID
        if (empty($data[0]['external_project_id'])) {
            $this->releaseManagementModel->insertData($id);
            $data = $this->releaseManagementModel->environmentview($id);
        }
        // Add the 'action' parameter to the data, which may specify an operation edit and view
        $data[0]["action"] = $action;
        // Render the 'environmentView' with the data,
        return $this->template_view('releaseManagement/environmentView', [$data, $usersbyroleid, $usersbyproject], 'Environment Setup', ['Environment Setup' => ASSERT_PATH . 'release-management/environmentSetup', 'Environment View' => ASSERT_PATH . 'release-management/environmentView']);
    }

    /**
     * ReleaseManagementController  
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     Function to update environment configurations
     * @return array
     */
    function environmentUpdate($id)
    {
        $data = array();
        for ($i = 1; $i <= 5; $i++) {
            $datas = [];
            $datas['environment_type'] = $this->request->getPost("environment_type{$i}");
            $datas['git_url'] = $this->request->getPost("git_url{$i}");
            $datas['jenkins_url'] = $this->request->getPost("jenkins_url{$i}");
            $datas['ip_address'] = $this->request->getPost("ip_address{$i}");
            $datas['server_url'] = $this->request->getPost("server_url{$i}");
            $datas['authority'] = implode(",", $this->request->getPost("authority{$i}"));

            $validationErrors = $this->hasInvalidInput($this->branchurlTracker, $datas);
            if ($validationErrors !== true) {
                return $this->response->setJSON([
                    'status' => 'false',
                    'message' => $validationErrors
                ]);
            }

            $data["environment_type{$i}"] = $datas['environment_type'];
            $data["git_url{$i}"] = $datas['git_url'];
            $data["jenkins_url{$i}"] = $datas['jenkins_url'];
            $data["ip_address{$i}"] = $datas['ip_address'];
            $data["server_url{$i}"] = $datas['server_url'];
            $data["authority{$i}"] = $datas['authority'];
        }
        $data['id'] = $id;

        // Call the model to update environment data 
        $this->branchurlTracker->environmentUpdate($data);
        // Reload the environment setup data after the update
        $data = $this->releaseManagementModel->environmentSetup(session()->get("getuserproject"));
        //  print_r($data);die;
        // Return a success response as JSON to confirm the update
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     Function to update log details
     * @return array
     */
    function logdetailsUpdate($id)
    {
        $data = array(
            'bucket_name' => $this->request->getPost('bucket_name'),
            'access_key' => $this->request->getPost('access_key'),
            'secret_key' => $this->request->getPost('secret_key'),
            'region' => $this->request->getPost('region'),
            'users' => $this->request->getPost('users'),
            'id' => $id
        );
        $validationErrors = $this->hasInvalidInput($this->releaseManagementModel, $data);
        if ($validationErrors !== true) {
            return $this->response->setJSON([
                'status' => 'false',
                'message' => $validationErrors
            ]);
        }
        $result = $this->releaseManagementModel->logdetailsUpdate($data);
        if ($result) {
            return $this->response->setJSON([
                'status' => 'success'
            ]);
        }
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     Function to search projects
     * @return array
     */
    function searchProject()
    {
        // Retrieve JSON input data from the request
        $jsonInput = $this->request->getJSON(true);
        // Extract the search query from the JSON data if it exists
        $searchQuery = isset($jsonInput['searchQuery']) ? $jsonInput['searchQuery'] : '';
        // If a search query is provided, search the project data
        if (!empty($searchQuery)) {
            // Call the model to search projects by the query and store the result
            $filteredData = $this->releaseManagementModel->searchProject($searchQuery, session()->get("getuserproject"));
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $filteredData
            ]);
        }
        // If no search query is provided, retrieve and return all environment setup data
        elseif (empty($searchQuery)) {
            $data = $this->releaseManagementModel->environmentsetup(session()->get("getuserproject"));
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            // Return an error response if the search query is invalid
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid search query'
            ]);
        }
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     Function to filter
     * @return array
     */
    function environmentFilter()
    {
        // Retrieve filter criteria data from POST request
        $filterData = $this->request->getPost();
        // Check if the filter data is empty
        if ($filterData === null) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data cannot submit with empty.'
            ]);
        }
        // Use the filter criteria to get filtered project data from the model
        $filteredData = $this->releaseManagementModel->environmentFilter($filterData, session()->get("getuserproject"));
        // If filtered data is available, return it as a success response in JSON format
        if (!empty($filteredData)) {
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $filteredData
            ]);
        } else {
            // If no matching projects are found, return an error message in JSON format
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'No projects found based on the applied filters.'
            ]);
        }
    }

    /** ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthick raja
     * @created     01 November 2024
     * @purpose     To fetch the changes made in a commitID
     * @return array
     */
    function commitdetails()
    {
        chdir("C:/xampp/htdocs/Git-check/server2/server/server");
        $input = json_decode(file_get_contents('php://input'), true);
        $commit = $input['commit'];
        if (is_array($commit)) {
            $commitString = implode(" ", $commit);
            $comment = "git show " . $commitString;
        } else {
            $comment = "git show " . $commit;
        }

        $output = shell_exec($comment);

        header('Content-Type: application/json');
        echo ($output);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthick raja
     * @created     01 November 2024
     * @purpose     Request to build the job in jenkins 
     * @return array
     */
    function jenkins()
    {
        $cache = Services::cache();
        $cachedValue = $cache->get('groupedJenkinResponse');

        $pages = count($cachedValue);
        $data = json_decode(file_get_contents('php://input'), true);

        $responses = [];
        // foreach ($input as $data) {
        //     // Process input data
        $commitId = trim($data['commit_id'], "\"");
        $commitid = $commitId . "fetch";
        $backlog = $data['backlog'] . "fetch";
        $sprint = $data['sprint'] . "fetch";
        $branch = $data['branch'] . "fetch";

        // Define Jenkins job URL with parameters
        // $url = "http://<JENKINS_URL>/job/RevolutionTest/buildWithParameters?" . 
        //        http_build_query([
        //            'token' => 'TacoTuesday',
        //            'commitid' => $commitid,
        //            'backlog' => $backlog,
        //            'sprint' => $sprint,
        //            'branch' => $branch
        //        ]);

        // // Initialize cURL
        // $ch = curl_init($url);

        // // Set cURL options for a POST request
        // curl_setopt($ch, CURLOPT_POST, true);
        // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Get response as a string
        // curl_setopt($ch, CURLOPT_USERPWD, "USERNAME:APITOKEN"); // Replace USERNAME and APITOKEN

        // // Execute the request
        // $response = curl_exec($ch);

        // // Check for errors
        // if (curl_errno($ch)) {
        //     $responses[] = ['error' => curl_error($ch)];
        // } else {
        $responses[] = [
            'commitid' => $commitid,
            'backlog' => $backlog,
            'sprint' => $sprint,
            'branch' => $branch,
            'pages' => $pages,
            'message' => 'Triggered Jenkins job successfully.'
        ];
        // }

        // Close the cURL session
        // curl_close($ch);
        // }

        header('Content-Type: application/json');
        echo json_encode($responses);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     fetching commit history
     * @return json
     */
    function commitHistoryFetch()
    {
        // Retrieve the selected product, sprint, and backlog IDs from the session
        $productId = session()->get('selected_product_id');
        $sprintId = session()->get('selected_sprint_id');
        $backlogId = session()->get('selected_backlog_id');
        $data = $this->releaseManagementModel->commitHistoryFetch($productId, $sprintId, $backlogId);
        return $this->response->setJSON([
            'status' => 'success',
            'data1' => $data
        ]);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     Insert commit history
     * @return array
     */
    function commitHistoryInsert()
    {
        // Retrieve the selected product ID from the session
        $productId = session()->get('selected_product_id');
        // Retrieve the employee ID from the session 
        $employee_id = session()->get('employee_id');
        // Get the raw JSON input from the request and decode it into an associative array
        $input = json_decode(file_get_contents('php://input'), true);
        // print_r($input);
        // die;
        // Fetch the author's name based on the employee ID 
        $author_name = $this->releaseManagementModel->fetchAuthor($employee_id);
        foreach ($input as $data) {
            $data = array(
                'author' => $author_name[0]['Author'],
                'product_id' => $productId,
                'sprint_id' => $data['sprint_id'],
                'backlog_id' => $data['backlog_id'],
                'no_of_files' => $data['num_of_files'],
                'from_branch' => $data['from_branch'],
                'from_commit_id' => $data['from_commit_id'],
                'to_branch' => $data['to_branch'],
                'to_commit_id' => str_replace('"', '', $data['to_commit_id'])
            );

            $data = $this->releaseManagementModel->commitHistoryInsert($data);
        }
        return $this->response->setJSON([
            'status' => 'success'
        ]);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     filtering history
     * @return array
     */
    function commithistoryFilter()
    {
        // Retrieve the selected product ID from the session
        $productId = session()->get('selected_product_id');
        // Retrieve the selected sprint ID from the session
        $sprintId = session()->get('selected_sprint_id');
        // Retrieve the selected backlog ID from the session
        $backlogId = session()->get('selected_backlog_id');
        $filterData = $this->request->getPost();
        //   print_r($filterData);die;
        // Check if the filter data is empty
        if (empty($filterData)) {
            // If no filter data is provided, return an error response in JSON format
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data cannot submit with empty.'
            ]);
        }
        // Use the filter criteria to get filtered project data from the model
        $filteredData = $this->releaseManagementModel->commithistoryFilter($filterData, $productId, $sprintId, $backlogId);
        // If filtered data is available, return it as a success response in JSON format
        if (!empty($filteredData)) {
            return $this->response->setJSON([
                'status' => 'success',
                'data1' => $filteredData
            ]);

        } else {
            // If no matching data was found, return an error response in JSON format
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'No data found based on the applied filters.'
            ]);
        }
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthick raja
     * @created     05 November 2024
     * @purpose     to fetch the detail of the product_id and productname
     * @return array
     */
    public function logdetails()
    {
        $ex_emp_id = session()->get("employee_id");
        $logusers = $this->releaseManagementModel->loguserdata($ex_emp_id);

        $userservice = service("users");
        $usersprojects = $userservice->userProject($ex_emp_id, $logusers);

        return $this->template_view('releaseManagement/logdetails', $usersprojects, 'Log Details', ['Home' => ASSERT_PATH . 'dashboard/dashboardView']);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthick raja
     * @created     01 November 2024
     * @purpose     To fetch the folder from the S3 bucket
     * @return array
     */
    public function getserver()
    {

        $data = json_decode(file_get_contents('php://input'), true);

        if ($data["product_id"]) {
            $details = $this->releaseManagementModel->logdetailsdata($data["product_id"]);
        }
        if (!class_exists('Aws\S3\S3Client')) {
            echo json_encode([
                'status' => 'awserror',
                'message' => 'AWS SDK is not installed. Please install it using Composer.',
            ]);
            return;
        }



        if (!empty($details[0]['access_key'])) {
            $bucketName = $details[0]['bucket_name'];  // S3 Bucket name
            $region = $details[0]['region'];
            $key = $details[0]['access_key'];
            $secret = $details[0]['secret_key'];
            // Check if product_id is set (for top-level folders)
            if (!isset($data["objectkey"])) {
                $s3Client = new S3Client([
                    'region' => $region,
                    'version' => 'latest',

                    'credentials' => [
                        'key' => $key,
                        'secret' => $secret,
                    ],
                ]);

                try {
                    $result = $s3Client->listObjectsV2([
                        'Bucket' => $bucketName,
                        'Delimiter' => '/',
                    ]);

                    $folders = [];
                    if (!empty($result['CommonPrefixes'])) {
                        foreach ($result['CommonPrefixes'] as $prefix) {
                            $folders[] = [
                                'folder' => $prefix['Prefix'],
                                'subfolders' => [],
                                'files' => [],
                            ];
                        }
                    }

                    // Return the folder structure as JSON
                    echo json_encode($folders);
                } catch (AwsException $e) {
                    echo json_encode(["status" => "error", "message" => "Error listing folders: " . $e->getMessage()]);
                }
            }
            // If product_id is not set, fetch files under the specific object key (subfolders/files)
            else {
                $s3Client = new S3Client([
                    'region' => $region,
                    'version' => 'latest',
                    'credentials' => [
                        'key' => $key,
                        'secret' => $secret,
                    ],
                ]);

                $parentKey = $data["objectkey"];

                try {
                    // List objects under the specified prefix
                    $result = $s3Client->listObjectsV2([
                        'Bucket' => $bucketName,
                        'Prefix' => $parentKey,
                        'Delimiter' => '/',
                    ]);

                    $keys = [];
                    $subfolders = [];
                    $files = [];

                    // Fetch subfolders
                    if (!empty($result['CommonPrefixes'])) {
                        foreach ($result['CommonPrefixes'] as $prefix) {
                            $subfolders[] = [
                                'folder' => $prefix['Prefix'],
                                'subfolders' => [],
                                'files' => [],
                            ];
                        }
                    }

                    // Fetch files with size and date
                    if (!empty($result['Contents'])) {
                        foreach ($result['Contents'] as $object) {
                            if ($object['Key'] !== $parentKey) { // Skip the parent folder itself
                                $files[] = [
                                    'name' => $object['Key'],
                                    'size' => $object['Size'], // File size in bytes
                                    'lastModified' => $object['LastModified']->format('Y-m-d H:i:s'), // Last modified date
                                ];
                            }
                        }
                    }

                    // Return nested structure with subfolders and files
                    echo json_encode([
                        'subfolders' => $subfolders,
                        'files' => $files,
                    ]);

                } catch (AwsException $e) {
                    echo "Error listing objects: " . $e->getMessage();
                }
            }
        } else {
            echo json_encode(["status" => "set the access keys"]);
        }
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthick raja
     * @created     01 November 2024
     * @purpose     downloading the logs from S3 bucket to local server
     * @return array
     */
    public function logdownload()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data["product_id"])) {
            return $this->response->setJSON([
                "status" => "error",
                "message" => "Product ID is missing."
            ]);
        }

        $details = $this->releaseManagementModel->logdetailsdata($data["product_id"]);

        $bucketName = $details[0]['bucket_name'];
        $region = $details[0]['region'];
        $key = $details[0]['access_key'];
        $secret = $details[0]['secret_key'];

        $s3Client = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key' => $key,
                'secret' => $secret,
            ],
        ]);

        $links = [];
        $existingFiles = [];
        $hasTgz = false;
        $filedetails = 0;

        foreach ($data["files"] as $file) {
            $objectKey = $file;

            $directoryPath = "C:/xampp/htdocs/Clone/scrum_tool/logdownload";

            if (!is_dir($directoryPath)) {
                mkdir($directoryPath, 0777, true);
            }

            $name = str_replace('/', '_', $objectKey);
            $savePath = $directoryPath . DIRECTORY_SEPARATOR . $name;

            // Check if file exists locally
            if (file_exists($savePath)) {
                $existingFiles[] = 'http://localhost/Clone/scrum_tool/public/release-management/logdownloads/' . $name;
                continue;
            }

            $validationErrors = $this->hasInvalidInput($this->logdownloadHistory, $data);
            if ($validationErrors !== true) {
                return $this->response->setJSON([
                    'status' => 'false',
                    'message' => $validationErrors
                ]);
            }

            try {
                $fileExtension = strtolower(pathinfo($objectKey, PATHINFO_EXTENSION));
                if ($fileExtension === 'tgz') {
                    $hasTgz = true;
                }

                $result = $s3Client->getObject([
                    'Bucket' => $bucketName,
                    'Key' => $objectKey,
                ]);

                file_put_contents($savePath, $result['Body']);

                $history = [
                    "issue" => $data["issue"],
                    "issueid" => $data["issue_id"],
                    "reason" => $data["reason"],
                    "filename" => basename($data["filedetails"][$filedetails][0]),
                    "folder" => dirname($data["filedetails"][$filedetails][0]),
                    "filesize" => $data["filedetails"][$filedetails][1],
                    "createddate" => CustomHelpers::datetimeformat($data["filedetails"][$filedetails][2]),
                ];
                $filedetails++;

                $emp_id = session()->get("employee_id");
                $emp_name = session()->get("first_name");

                $this->logdownloadHistory->logdownloadHistory($history, $emp_id, $emp_name);

                $links[] = 'http://localhost/Clone/scrum_tool/public/release-management/logdownloads/' . $name;
            } catch (AwsException $e) {
                return $this->response->setJSON([
                    "status" => "error",
                    "message" => "Error downloading file '{$objectKey}': " . $e->getMessage()
                ]);
            }
        }

        $response = [
            "status" => "success",
            "links" => $links,
        ];

        if ($hasTgz) {
            $response["extension"] = "tgz";
        }

        if (!empty($existingFiles)) {
            $response["existingFiles"] = $existingFiles;
        }

        if (empty($links) && empty($existingFiles)) {
            $response = [
                "status" => "error",
                "message" => "No files were downloaded.",
            ];
        }

        return $this->response->setJSON($response); // Use setJSON for proper JSON response
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthik raja
     * @created     11 November 2024
     * @purpose     Delete files after downloading to local from server if its not tgz
     * @return array
     */
    public function deleteFiles()
    {
        // Get the list of files to delete from the request
        $data = json_decode(file_get_contents('php://input'), true);
        $files = $data['files'] ?? [];
        $folder = "C:/xampp/htdocs/Clone/scrum_tool/logdownload";

        foreach ($files as $file) {
            $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
            if (strtolower($fileExtension) === 'tgz') {
                echo "Skipping file (TGZ): $file\n";
                continue;
            }
            $filePath = $folder . DIRECTORY_SEPARATOR . basename($file);

            // Check if the file exists before deleting
            if (file_exists($filePath)) {
                // Delete the file
                if (unlink($filePath)) {
                    echo "Deleted file: $filePath\n";
                } else {
                    echo "Failed to delete file: $filePath\n";
                }
            } else {
                echo "File not found: $filePath\n";
            }
        }
    }



    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Karthick raja
     * @created     12 November 2024
     * @purpose     -downloads the file  locally from server,
     *              -if it is .tgz file renders a new page
     * @return array
     */
    public function localdownload($file)
    {
        $fileExtension = pathinfo($file, PATHINFO_EXTENSION);
        $name = basename($file);
        $directoryPath = "C:/xampp/htdocs/Clone/scrum_tool/logdownload";


        if (strtolower($fileExtension) === 'tgz') {
            try {
                $tgzFilePath = $directoryPath . DIRECTORY_SEPARATOR . $name;

                // Execute the tar command to list files
                $command = "tar -tvf " . escapeshellarg($tgzFilePath);
                $output = shell_exec($command);

                if ($output === null) {
                    throw new Exception("Error executing tar command or empty output.");
                }

                // Process the output into an array of file paths
                // $fileList = explode("\n", trim($output)); // Split output into lines
                $fileExplorer = $this->generateFileExplorerFromList($output);

                // Pass the file explorer HTML to the view
                return $this->template_view(
                    "releaseManagement/tgzdownload",
                    ['fileExplorer' => $fileExplorer],
                    $name,
                    null
                );
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            }
        } else {

            $folder = "C:/xampp/htdocs/Clone/scrum_tool/logdownload";
            $destinationPath = "C:\Users\Lenovo\Downloads";

            if (!is_dir($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }

            $savePath = $destinationPath . DIRECTORY_SEPARATOR . basename($file);

            $fileContent = file_get_contents($folder . DIRECTORY_SEPARATOR . $file);

            // Check if file content is fetched successfully
            if ($fileContent === false) {
                die("Error: Unable to fetch file content.");
            }

            // Save the file to the destination
            $writeStatus = file_put_contents($savePath, $fileContent);

            // Check if the file was successfully written
            if ($writeStatus === false) {
                die("Error: Unable to save the file to the destination.");
            }

            // Delete the original file after saving it to the destination
            $originalFilePath = $folder . DIRECTORY_SEPARATOR . $file;
            if (file_exists($originalFilePath)) {
                unlink($originalFilePath);  // Deletes the file
            } else {
                die("Error: Original file not found for deletion.");
            }

            // Return a success message
            return "File downloaded successfully to: " . $savePath . " and original file deleted in server.";
        }
    }


    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      karthick raja
     * @created     08 November 2024
     * @purpose     download directly from tgz without extracting
     * @return array
     */


    public function tgztolocaldownload()
    {
        // Read the POST data and decode it into a PHP array
        $data = json_decode(file_get_contents('php://input'), true);
        $file = $data["folder"];
        $foldername = $data["filename"];
        $file_info = pathinfo($foldername);
        $extension = $file_info['extension'];
        $filename = $file . "." . $extension;
        // print_r($filename);
        //     die;

        // Check if files are passed via POST
        if (!isset($data['files']) || !isset($data['filename'])) {
            echo "Invalid request. Missing required data.";
            return;
        }
        $validationErrors = $this->hasInvalidInput($this->logdownloadHistory, $data);
        if ($validationErrors !== true) {
            return $this->response->setJSON([
                'status' => 'false',
                'message' => $validationErrors
            ]);
        }

        // Path to the .tgz file
        $tgzFile = "C:/xampp/htdocs/Clone/scrum_tool/logdownload/" . $data["filename"];

        // Directory where you want to extract the files
        $extractDir = "C:\Users\S KARTHICK RAJA\Downloads";

        // Loop through each file in the 'files' array and extract it
        $filedetails = 0;
        foreach ($data['files'] as $file) {
            // Dynamically set the file to extract
            $fileToExtract = $data["folder"] . "/" . $file["name"]; // This will now be the actual file from the request

            // Build the tar command to extract the specific file
            $command = 'tar -xzf "' . $tgzFile . '" -C "' . $extractDir . '" "' . $fileToExtract . '"';

            // Execute the tar command
            exec($command, $output, $status);

            // Check the result of the extraction
            if ($status === 0) {
                $fetcheddata = $this->releaseManagementModel->loghistoryFetch($filename);

                $history = [
                    "issue" => $data["issue"],
                    "issueid" => $data["issue_id"],
                    "reason" => $data["reason"],
                    "filename" => $data["files"][$filedetails]['name'],
                    "folder" => $fetcheddata[0]['folder'] . '/' . $fetcheddata[0]['file'],
                    "filesize" => $data["files"][$filedetails]['size'],
                    "createddate" => CustomHelpers::alphadatetimeformat($data["files"][$filedetails]['date']),
                ];
                $filedetails = $filedetails + 1;
                $emp_id = session()->get("employee_id");
                $emp_name = session()->get("first_name");

                $this->logdownloadHistory->logdownloadHistory($history, $emp_id, $emp_name);
                return $this->response->setJSON([
                    'status' => 'success',
                    'message' => "Successfully extracted $fileToExtract\n"
                ]);
            } else {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => "Error extracting $fileToExtract\n"
                ]);
            }
        }
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      karthick raja
     * @created     08 November 2024
     * @purpose     generate files of Tgz in structure
     * @return array
     */
    public function generateFileExplorerFromList($input)
    {
        $lines = explode("\n", trim($input)); // Split the input by new lines
        $fileExplorer = [];

        foreach ($lines as $line) {
            // Split each line by spaces to get the details
            $parts = preg_split('/\s+/', $line);

            // Ensure we have at least 8 parts in the line (size, date, file name)
            if (count($parts) >= 8) {
                // Extract the file path (last part in the line)
                $path = end($parts);

                // Extract file size (assuming it's in the 4th index in the `ls` output format)
                $size = number_format((int) $parts[5] / 1024, 2) . 'kb'; // Convert from bytes to KB (assuming size is in bytes)

                // Extract the date (index 5, 6, and 7 for the day, month, and time)
                $date = $parts[6] . ' ' . $parts[7] . ' ' . $parts[8];

                // Extract the directory (folder) path
                $folder = dirname($path);

                // Initialize the folder in the file explorer array if it doesn't exist
                if (!isset($fileExplorer[$folder])) {
                    $fileExplorer[$folder] = [];
                }

                // Add the file with its details (name, size, date) to the appropriate folder
                $fileExplorer[$folder][$path] = "$path $size $date";
            }
        }

        // Return the file explorer array with folder structure
        return $fileExplorer;
    }




    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     17 November 2024
     * @purpose     log details fetching
     * @return array
     */
    public function logdownloadFetch()
    {
        // Fetch log details from the release management model
        $data = $this->releaseManagementModel->logdownloadFetch();

        // Return the fetched log details as a JSON response 
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     downlaod filter 
     * @return array
     */
    public function logdownloadFilter()
    {
        // Retrieve filter data from POST request
        $filterData = $this->request->getPost();
        // Check if the filter data is empty
        if (empty($filterData)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Data cannot submit with empty.'
            ]);
        }
        // Use the filter criteria to get filtered data from the model
        $filteredData = $this->releaseManagementModel->logdownloadFilter($filterData);
        // If filtered data is available, return it as a success response in JSON format
        if (!empty($filteredData)) {
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $filteredData
            ]);
        } else {
            // If no matching data are found, return an error message in JSON format
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'No data found based on the applied filters.'
            ]);
        }
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     downlaod report 
     * @return array
     */
    public function logdownloadReport()
    {
        $data = $this->releaseManagementModel->logdownloadReport();
        if (ob_get_length())
            ob_end_clean();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = $sheet->getHighestRow();
        # Define column headers and set column width
        $headers = [
            'A' => 'Folder',
            'B' => 'File Name',
            'C' => 'Total Downloads',
            'D' => 'File Size',
            'E' => 'File Created Date',
            'F' => 'Issue Id',
            'G' => 'Employee Name'

        ];
        # Set headers and column widths
        $columnIndex = 1;
        foreach ($headers as $column => $header) {
            $sheet->setCellValue("{$column}1", $header);
        }
        $sheet->getColumnDimension('A')->setWidth(50);
        $sheet->getColumnDimension('B')->setWidth(50);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(50);
        $sheet->getColumnDimension('E')->setWidth(50);
        $sheet->getColumnDimension('F')->setWidth(50);
        $sheet->getColumnDimension('G')->setWidth(50);
        $sheet->getStyle('A')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(40);
        foreach (range('B', 'G') as $col) {
            $sheet->getStyle($col)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
        # Apply header row styles (background color and font color)
        $headerRange = 'A1:G1';
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF031D5B');
        $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
        # Add data to rows
        $rowNumber = 2;
        foreach ($data as $value) {
            $sheet->setCellValue("A{$rowNumber}", $value['folder']);
            $sheet->setCellValue("B{$rowNumber}", $value['file']);
            $sheet->setCellValue("C{$rowNumber}", $value['Total_downloads']);
            $sheet->setCellValue("D{$rowNumber}", $value['file_size']);
            $sheet->setCellValue("E{$rowNumber}", $value['file_created_date']);
            $sheet->setCellValue("F{$rowNumber}", $value['issue_id']);
            $sheet->setCellValue("G{$rowNumber}", $value['employee_name']);

            # Optional: apply row styles
            if ($rowNumber) {
                # Style alternate rows
                $sheet->getStyle("A{$rowNumber}:G{$rowNumber}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getStyle("A{$rowNumber}:G{$rowNumber}")->getFont()->getColor()->setARGB(Color::COLOR_BLACK);
                $sheet->getRowDimension($rowNumber)->setRowHeight(20);
                $sheet->getStyle($rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
            $rowNumber++;
        }
        # Save the Excel file
        $writer = new Xlsx($spreadsheet);
        $filename = 'C:\xampp\htdocs\scrum_tool\Report\report.xlsx';
        $writer->save($filename);
        return $this->response->setJSON([
            'status' => 'success',
            'data' => REPORT_PATH . 'report.xlsx'
        ]);
    }


    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     generate otp 
     * @return array
     */
    public function generateOtp()
    {
        // Generate a random 6-digit OTP
        $otp = rand(100000, 999999);
        // Store the generated OTP in the session for later verification
        session()->set(['otp' => $otp]);
        $this->otp->emailTrigger($otp);
    }

    /**
     * ReleaseManagementController
     * 
     * @category    Controller
     * @package     App\Controllers
     * @author      Deepika sakthivel
     * @created     01 November 2024
     * @purpose     verify otp 
     * @return array
     */
    public function verifyOtp()
    {
        // Decode the JSON  and convert it to an associative array
        $data = json_decode(file_get_contents('php://input'), true);
        // Retrieve the OTP provided by the user from the request data
        $userOtp = $data['otp'];
        // Retrieve the OTP stored in the session for comparison
        $sessionOtp = session()->get('otp');
        // Check if both the user-provided OTP and the session OTP exist and match
        if ($userOtp && $sessionOtp && $userOtp == $sessionOtp) {
            // Remove the OTP from the session to prevent reuse
            session()->remove('otp');
            // Return a JSON response indicating success
            return json_encode(['status' => 'success', 'message' => 'OTP verified successfully']);
        }
        // If the OTP is invalid or does not match, return an error response
        return json_encode(['status' => 'error', 'message' => 'Invalid OTP']);
    }
}
?>