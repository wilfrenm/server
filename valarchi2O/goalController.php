<?php

namespace App\Controllers;

use App\Helpers\CustomHelpers as DateHelper;
use CodeIgniter\HTTP\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use Config\GoalDashboardModelConfig;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Predis\Command\Redis\APPEND;

class GoalsController extends BaseController
{
    protected $goalsModelObj;
    protected $scrumGoalsModelObj;
    protected $scrumGoalTasksModelObj;
    protected $scrumGoalsFeedbackModelObj;
    protected $scrumPeopleMonthlyRatingObj;
	public $managementController;
    protected $ScrumGoalsCategoryModelObj;

    public function __construct()
    {
        //get employee id in session
        $this->employeeId = session()->get('employee_id');
        // Check if the class exists before creating the object
        $this->goalsModelObj = $this->checkClassPath(\App\Models\Goals\GoalsModel::class);
        $this->scrumGoalsModelObj = $this->checkClassPath(\App\Models\Goals\ScrumGoalsModel::class);
        $this->scrumGoalTasksModelObj = $this->checkClassPath(\App\Models\Goals\ScrumGoalTasksModel::class);
        $this->scrumGoalsFeedbackModelObj = $this->checkClassPath(\App\Models\Goals\ScrumGoalsFeedbackModel::class);
        $this->scrumPeopleMonthlyRatingObj = $this->checkClassPath(\App\Models\Goals\ScrumPeopleMonthlyRating::class);
        $this->ScrumGoalsCategoryModelObj = $this->checkClassPath(\App\Models\Goals\ScrumGoalsCategoryModel::class);
    }

    /*
     * @category   GOALS LIST 
     * @author     KARVINBRITTO M
     * @created    27 OCT 2024
     */

    /**
     * @author KARVINBRITTO M
     * @return Response/Array
     * Purpose: This function is used to get Goals details with filters applied
     */
    public function goalsList($status = null)
    {
        //To handle if filter data is empty
        $temp_filter_data = [
            'stakeholder' => "",
            'objective' => "",
            'category' => "",
            'start_date' => "",
            'end_date' => "",
            'status' => "",
            'searchQuery' => ""
        ];
        $filter = $this->request->getPost();
        $filter_data_status = true;
        if (!$filter) {
            if (!empty($status)) {
                $temp_filter_data['status'] = $status;
            }
            $filter_data_status = false;
            $filter = $temp_filter_data;
        }
        //Breadcrumbs for navigation
        $breadcrumbs = [
            'Home' => ASSERT_PATH . 'dashboard/goalDashboardView',
            'Goals List' => ASSERT_PATH . "goals/goalsList"
        ];

        $goalsList = $this->goalsModelObj->getFilterGoalsList($filter, $this->employeeId);
        $goalStatus = $this->goalsModelObj->getGoalStatus();
        $stakeholder = $this->goalsModelObj->getStakeholder();
        $category = $this->goalsModelObj->getCategory();
        $objective = $this->goalsModelObj->getObjective();
        $rating = $this->goalsModelObj->getRatings();

        $data = [
            'goalsList' => $goalsList,
            'status' => $goalStatus,
            'stakeholder' => $stakeholder,
            'category' => $category,
            'objective' => $objective,
            'rating' => $rating
        ];

        //If filter data is there set JSON 
        if ($filter_data_status) {
            return $this->response->setJSON($goalsList);
        }
        //If filter data array is empty Return the view for shwing goals list.
        return $this->template_view("goals/goalsList", $data, "Goals List", $breadcrumbs);
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to create or Update the Goal 
     */
    public function saveGoal(): Response
    {
        // Extract goal data from the request
        $goalData = $this->request->getPost();
        $goalId = $this->request->getPost('id'); // Check if it's an update

        // Handle optional fields
        $objective = $this->request->getPost('r_objective_id') ?: null;
        $category = $this->request->getPost('r_category_id') ?: null;
        $newCategoryName = $this->request->getPost('new_category_name') ?: null;
        $effectiveSpentHours = $this->request->getPost('effective_spent_hours') ?: null;
        $actualEndDate = null;

        // Set actual end date if status is 'completed'
        if ($this->request->getPost('r_goal_status_id') == 36) {
            $actualEndDate = date("Y-m-d");
        }
        // If 'Others' is selected, save the new category
        if ($category === 'others' && !empty($newCategoryName)) {
            // Insert new category into the database and get its ID
            $categoryData = [
                'category_name' => $newCategoryName,
                'r_objective_id' => $objective
            ];

            //For Backend Validations
            $validationErrors = $this->hasInvalidInput($this->ScrumGoalsCategoryModelObj, $categoryData);
            if ($validationErrors !== true) {
                return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
            }
            //call the insertCategory function. Use the new category ID for the goals table
            $category = $this->ScrumGoalsCategoryModelObj->insertCategory($categoryData);
        }
        // Prepare goal data to be inserted or updated
        $goalData['r_objective_id'] = $objective;
        $goalData['r_category_id'] = $category;
        $goalData['r_created_user'] = $this->employeeId;
        $goalData['r_assigned_user'] = $this->employeeId;
        $goalData['effective_spent_hours'] = $effectiveSpentHours;
        $goalData['actual_end_date'] = $actualEndDate;

        // Validate the goal data
        $validationErrors = $this->hasInvalidInput($this->scrumGoalsModelObj, $goalData);
        if ($validationErrors !== true) {
            return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
        }
        // Check if it's an update or insert based on the presence of goal_id
        if ($goalId) {
            // Update goal
            $result = $this->scrumGoalsModelObj->updateGoal($goalData);
        } else {
            // Insert goal
            $result = $this->scrumGoalsModelObj->addGoal($goalData);
        }
        // $result = $this->scrumGoalsModelObj->saveGoal($goalData, $goalId);
        if ($result) {
            $message = $goalId ? 'Goal updated successfully' : 'Goal added successfully';
            return $this->response->setJSON(['success' => true, 'message' => $message]);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to save Goal']);
        }
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to give Rating  of the Goals 
     */
    public function selfRating(): Response
    {
        $selfratingData = $this->request->getPost();
        $goal_id = $this->request->getPost('id');
        $self_feedback = $this->request->getPost('selffeedback');
        $feedbackData = [
            'r_goal_id' => $goal_id,
            'r_user_id' => $this->employeeId,
            'feedback' => $self_feedback
        ];
        //For backend validations
        $validationErrors = $this->hasInvalidInput($this->scrumGoalsFeedbackModelObj, $feedbackData);
        if ($validationErrors !== true) {
            return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
        }
        // get last insert feedback id
        $lastInsertFeedbackId = $this->scrumGoalsFeedbackModelObj->insertSelfRating($feedbackData);
        if (!$lastInsertFeedbackId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid feedback Data']);
        }
        $selfratingData['goal_id'] = $goal_id;
        $selfratingData['employee_feedback_id'] = $lastInsertFeedbackId;
        $selfratingData['r_goal_status_id'] = 39;
        //For backend validations
        $validationErrors = $this->hasInvalidInput($this->scrumGoalsModelObj, $selfratingData);
        if ($validationErrors !== true) {
            return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
        }
        //call the updateselfRating function
        $feedback_update = $this->scrumGoalsModelObj->updateselfRating($selfratingData);

        if ($feedback_update) {
            return $this->response->setJSON(['success' => true, 'message' => 'Rating added successful']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to added Rating']);
        }
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to delete the Goals
     */
    public function deleteGoal(): Response
    {
        $id = $this->request->getPost('id');
        $result = $this->goalsModelObj->deleteGoal($id);

        if ($result) {
            return $this->response->setJSON(['success' => true, 'message' => 'Goal Deleted successfully']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to Delete Goal']);
        }
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to check Goals Category name already Exists or not 
     */
    public function checkCategoryExists(): Response
    {
        $categoryName = $this->request->getPost('category_name');
        $categoryData = ['categoryName' => $categoryName];
        $result = $this->goalsModelObj->getCategory($categoryData);

        if ($result) {
            return $this->response->setJSON(['success' => true]);
        } else {
            return $this->response->setJSON(['success' => false]);
        }
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to get the Goals objective and catgory by stakeholder
     */
    public function dynamicStakeholder(): Response
    {
        $type = $this->request->getPost('type');
        //set dfault response
        $response = ['success' => false, 'message' => 'Invalid request', 'data' => []];

        if ($type === 'objective') {
            $stakeholderId = $this->request->getPost('stakeholder_id');
            if ($stakeholderId) {
                $objectives = $this->goalsModelObj->getObjective($stakeholderId);
                if ($objectives) {
                    // Format objectives as HTML options
                    $options = '<option value="">Select Objective</option>';
                    foreach ($objectives as $objective) {
                        $options .= '<option value="' . $objective['objective_id'] . '">' . $objective['objective_name'] . '</option>';
                    }
                    $response = ['success' => true, 'data' => ['objectives' => $options]];
                } else {
                    $response['message'] = 'No objectives found for the selected stakeholder';
                }
            }
        } elseif ($type === 'category') {
            $objectiveId = $this->request->getPost('objective_id');
            if ($objectiveId) {
                $objectiveData = ['objectiveId' => $objectiveId];
                $categories = $this->goalsModelObj->getCategory($objectiveData);
                if ($categories) {
                    // Format categories as HTML options
                    $options = '<option value="">Select Category</option>';
                    foreach ($categories as $category) {
                        $options .= '<option value="' . $category['category_id'] . '">' . $category['category_name'] . '</option>';
                    }
                    $options .= '<option value="others"> Others </option>';
                    $response = ['success' => true, 'data' => ['categories' => $options]];
                } else {
                    $response['message'] = 'No categories found for the selected objective';
                }
            }
        }

        // Send response as JSON
        return $this->response->setJSON($response);
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to insert Goals 
     */
    public function importGoals(): Response
    {
        // Handle file upload
        $file = $this->request->getFile('importGoal');
        if (!in_array($file->getClientExtension(), ['xls', 'xlsx'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid file format. Please upload an Excel file.']);
        }

        // Define the file path and remove any previous file with the same name to avoid conflict
        $filePath = WRITEPATH . 'uploads/' . $file->getName();
        // Move the uploaded file to a temporary location
        $file->move(WRITEPATH . 'uploads/', $file->getName());

        // Recreate the file path after moving it
        $filePath = WRITEPATH . 'uploads/' . $file->getName();

        // Load the Excel file
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        // Skip header row and process data
        $errorRows = [];
        $successRows = [];
        $errorMessage = [];

        for ($i = 1; $i < count($rows); $i++) {
            $goalName = $rows[$i][0];
            $startDate = $rows[$i][1];
            $endDate = $rows[$i][2];
            $stakeholderName = $rows[$i][3];
            //check stakholder
            $stakeholderData = '';
            if ($stakeholderName) {
                // Get stakeholder ID based on the stakeholder name
                $stakeholderData = $this->goalsModelObj->getStakeholder($stakeholderName);
            }

            if (!$stakeholderData) {
                $errorMessage[] = [
                    'row' => $i + 1,
                    'errors' => ['Invalid stakeholder: ' . $stakeholderName]
                ];
                $errorRows[] = $i + 1;
                continue; // Continue to the next row
            }

            $stakeholderId = $stakeholderData[0]['stakeholder_id'];

            // Prepare data to insert into the scrum_goals table
            $goalsData = [
                'goal_name' => $goalName,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'r_stakeholder_id' => $stakeholderId,
                'r_objective_id' => null,
                'r_category_id' => null,
                'r_created_user' => $this->employeeId,
                'r_assigned_user' => $this->employeeId,
                'r_goal_status_id' => 37
            ];

            // For Backend Validations
            $validationErrors = $this->hasInvalidInput($this->scrumGoalsModelObj, $goalsData);
            if ($validationErrors !== true) {
                $errorMessage[] = [
                    'row' => $i + 1,
                    'errors' => $validationErrors
                ];
                $errorRows[] = $i + 1;
                continue; // Continue to the next row
            }

            // Insert data into the database
            $result = $this->scrumGoalsModelObj->addGoal($goalsData);
            if (!$result) {
                $errorMessage[] = [
                    'row' => $i + 1,
                    'errors' => ['Database insertion failed for row ' . ($i + 1)]
                ];
                $errorRows[] = $i + 1;
                continue; // Continue to the next row
            }

            // If successful, add the row number to successRows
            $successRows[] = $i + 1;
        }

        // Return the response with success and error rows
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Import file processed successfully.',
            'data' => [
                    'successRows' => $successRows,
                    'errorRows' => $errorRows,
                    'reason' => $errorMessage
                ]
        ]);
    }

    /**
     * @author KARVINBRITTO M
     * @return Response
     * Purpose: This function is used to download the sample import goals excel file
     */
    public function downloadSampleFile()
    {
        // Path to the sample file
        $filePath = WRITEPATH . 'uploads/Sample-GoalsData .xlsx'; // Adjust this path as needed

        // Check if file exists
        if (file_exists($filePath)) {
            // Force the file to be downloaded
            return $this->response->download($filePath, null)->setFileName('sample_goals_template.xlsx');
        } else {
            // File does not exist, return a 404 or error
            log_message('error', " Sample file not found..");
            return redirect()->back();
        }
    }



    /*
     * @category   GOAL TASK 
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */

    // Goal Task List  Method
    public function goalTaskList($goalId)
    {
        // Define breadcrumbs for navigation in the view 
        $breadcrumbs = [
            'Home' => ASSERT_PATH . 'dashboard/goalDashboardView',
            'Goal List' => ASSERT_PATH . 'goals/goalsList',
            'Goal Task List' => ASSERT_PATH . "goals/taskList"
        ];

        // Validate the goalId before proceeding and return goal name and goal status and end date
        $goalDetails = $this->goalsModelObj->getGoalDetails($goalId);

        if (empty($goalDetails) || $goalDetails[0]["r_assigned_user"] != $this->employeeId) {
            // Return an error response if goalId is invalid
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'Invalid Goal ID provided.'
            ]);
        }

        //handle when post data is empty
        $temp_filter = [
            "goalID" => $goalId,
            "fromDate" => "",
            "toDate" => "",
            "filterStatus" => "",
            "searchQuery" => ""
        ];

        $filter = $this->request->getPost();

        $filterAppliedStatus = true;  //if true means filter applied
        if (!$filter) {
            $filterAppliedStatus = false; //it means filter not applied
            $filter = $temp_filter;
        }
        // Get the goal details (like goal name and status) from the database based on the provided goalId
        $tasklist = $this->goalsModelObj->filterGoalTaskItem($filter);

        $goalName = $goalDetails[0]["goal_name"];
        $goalStatusId = $goalDetails[0]["status_id"];
        $end_date = $goalDetails[0]["end_Date"];


        $taskModuleId = 23;  // Set the specific module ID for goal tasks

        $data = array(
            "taskList" => $tasklist,
            "status" => $this->goalsModelObj->getGoalTaskStatus($taskModuleId),
            "goalName" => $goalName,
            "goalStatusId" => $goalStatusId,
            "end_date" => $end_date,
            "goalId" => $goalId  // Store the goal ID itself
        );
        // Return the view, passing the necessary data (task list, goal name, status, etc.)

        if ($filterAppliedStatus) {
            return $this->response->setJSON($data["taskList"]);
        }
        // This will render the "taskList" view with the data passed to it
        return $this->template_view("goals/taskList", $data, "Goal Tasks List", $breadcrumbs);
    }


    /*
     * @category   GOAL TASK MANAGEMENT
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */

    public function manageGoalTask($action)
    {
        // Get the POST data 
        $data = $this->request->getPost();

        // Initialize a default response indicating failure and an invalid action
        $response = ['success' => false, 'message' => 'Invalid action'];

        // Perform different operations based on the action passed to the function
        switch ($action) {
            case 'add':
                // Validate the goal data
                $validationErrors = $this->hasInvalidInput($this->scrumGoalTasksModelObj, $data);

                //For Backend Validations
                if ($validationErrors !== true) {
                    return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
                } else {
                    // Call the model method to insert a new task into the database
                    $insertId = $this->scrumGoalTasksModelObj->addTask($data);

                    // Prepare the response based on whether the insert was successful
                    $response = [
                        'success' => (bool) $insertId, // True if $insertId is valid (non-zero), false otherwise
                        'message' => $insertId ? 'Task added successfully' : 'Failed to add task'
                    ];
                }
                break;

            case 'edit':

                // Validate the goal data
                $validationErrors = $this->hasInvalidInput($this->scrumGoalTasksModelObj, $data);

                //For Backend Validations
                if ($validationErrors !== true) {
                    return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
                } else {
                    // Call the model method to update the task details in the database
                    $updated = $this->scrumGoalTasksModelObj->updateTask($data);

                    // Prepare the response based on whether the update was successful
                    $response = [
                        'success' => (bool) $updated, // True if $updated is valid (non-zero), false otherwise
                        'message' => $updated ? 'Task updated successfully' : 'Failed to update task'
                    ];
                }
                break;

            case 'delete':
                // Extract the task ID from the POST data; default to null if 'id' is not present
                $goalTaskId = $data['id'] ?? null;

                if ($goalTaskId) {
                    // Call the model method to delete the task from the database
                    $deleted = $this->goalsModelObj->deleteGoalTask($goalTaskId);

                    // Prepare the response based on whether the delete was successful
                    $response = [
                        'success' => (bool) $deleted, // True if $deleted is valid (non-zero), false otherwise
                        'message' => $deleted ? 'Task deleted successfully' : 'Failed to delete task'
                    ];
                } else {
                    // If 'id' is missing, update the response message
                    $response['message'] = 'Task ID not provided';
                }
                break;

            default:
                // Handle unsupported actions by updating the response message
                $response['message'] = 'Unsupported action';
                break;
        }

        // Return the response as a JSON object 
        return $this->response->setJSON($response);
    }


    /*
     * @category   GOAL BOARD 
     * @author     Abishek R
     * @created    27 OCT 2024
     */

    public function getGoalReview()
    {
        // Take the employee id from the session
        $id = $this->employeeId;
        $currentuser = $this->goalsModelObj->getGoalname($id);
        $currentuser = array_column($currentuser, 'first_name')[0];
        $userService = service("users");
        $usersByParentId = $userService->getUsersByParentId($id);
        $breadcrumbs = [
            'Home' => ASSERT_PATH . 'dashboard/goalDashboardView',
            "Goal Board " => ''
        ];
        $goallist = [];
        $usersByParentId = $userService->getUsersByParentId($id);
        $usersByParentId[]['id'] = $id;
        foreach ($usersByParentId as $key => $value) {
            $goallist[] = $this->goalsModelObj->getGoalReview($value['id']);
        }
        // Filter out empty arrays
        function removeEmptyArraysgoal($array)
        {
            return array_filter($array, function ($item) {
                return !empty($item); // Filter out empty arrays
            });
        }
        $goallist = removeEmptyArraysgoal($goallist);
        $goallist = array_merge(...$goallist); //merge multiple arrays into a single array
        $goalrating = $this->goalsModelObj->getGoalRating();
        $goalvalue = DateHelper::formatDatesInArray($goallist);
        $goalStatus = $this->goalsModelObj->getGoalStatus();
        array_pop($usersByParentId);
        $topmanager = ['id' => $id, 'firstname' => $currentuser];
        $data = [
            'goal_name' => $goalvalue,
            'goal_rating' => $goalrating,
            'manager' => $usersByParentId,
            'currentuser' => $id,
            'goalstatus' => $goalStatus,
            'top' => $topmanager,
            'people_rating' => $this->goalsModelObj->peopleRating(),
            'usersByParentId' => $usersByParentId
        ];
        return $this->template_view('goals/goalReview', ['goal' => $data], "Goal Board", $breadcrumbs);
    }

    /**
     * @author Abishek R
     * @return Response
     * Purpose:InreviewGoal individual feedback
     */
    public function goalupdate()
    {
        $id = $this->employeeId;
        $reviewData = $this->request->getPost('reviewData');
        $reviewData = json_decode($reviewData, 1);
        foreach ($reviewData as $key => $value) {
            $goalid = $value['goal_id'];
            $updaterating = $value['rating_id'];
            $updatefeedback = $value['feedback'];
            $this->goalsModelObj->updategoalboard($goalid, $updaterating, $updatefeedback, $id);
        }
        if ($reviewData) {
            return $this->response->setJSON(['status' => 'success', 'message' => 'Review data received']);
        } else {
            return $this->response->setJSON(['status' => 'error', 'message' => 'Invalid data']);
        }
    }
    /**
     * @author Abishek R
     * @return Response
     * Purpose:Filter Data
     */
    public function goalboardfilter()
    {
        $filtervalue = $this->request->getPost();
        $id = $this->employeeId;
        $userService = service("users");
        $usersByParentId = $userService->getUsersByParentId($id);
        $usersByParentId[]['id'] = $id;
        $user = [];
        foreach ($usersByParentId as $index => $value) {
            $user[] = $this->goalsModelObj->goalboardfilter($filtervalue, $value['id']);
        }
        function removeEmptyArraysfilter($array)
        {
            return array_filter($array, function ($item) {
                return !empty($item);
            });
        }
        $goallist = removeEmptyArraysfilter($user);
        $goallist = array_merge(...$goallist);
        $goalvalue = DateHelper::formatDatesInArray($goallist);
        return $this->response->setJSON($goalvalue);

        $goallist = removeEmptyArraysfilter($user);
        $goallist = array_merge(...$goallist);
        $goalvalue = DateHelper::formatDatesInArray($goallist);
        return $this->response->setJSON($goalvalue);
    }



    /*
     * @category   GOAL BOARD Monthly rating Update
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */


    public function monthlyRatingUpdate()
    {
        $data = $this->request->getPost();
        if (empty($data)) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Data not received.'
            ]);
        }
        // Process month and year from input
        $monthYear = $data['monthYear'];
        $monthYearArray = explode('-', $monthYear);
        $data['month'] = $monthYearArray[1];
        $data['year'] = $monthYearArray[0];

        // Check if goals exist for the specified month and year
        $goals = $this->goalsModelObj->goalsCountMonthlyRating($data);
        if (empty($goals)) {
            return $this->response->setJSON([
                'success' => 'nogoal',
                'message' => 'Goals not available for the specified month and year.'
            ]);
        }
        if ($goals[0]["assigned_goals_count"] == $goals[0]["review_completed_count"]) {
            // Check for existing ratings
            $existingRow = $this->goalsModelObj->checkAvailablity($data);

            // Block if self-rating already exists for the same month
            if ($data["ratingType"] === "self" && !empty($existingRow["self_monthly_rating_id"])) {
                return $this->response->setJSON([
                    'success' => 'exists',
                    'message' => 'Self rating already provided for this month.'
                ]);
            }

            // Block if manager-rating already exists for the same month
            if ($data["ratingType"] === "manager" && !empty($existingRow["reviewer_monthly_rating_id"])) {
                return $this->response->setJSON([
                    'success' => 'exists',
                    'message' => 'Manager rating already provided for this month.'
                ]);
            }

            // Handle self-rating: insert
            if ($data["ratingType"] === "self") {
                // Validate the goal data
                $validationErrors = $this->hasInvalidInput($this->scrumPeopleMonthlyRatingObj, $data);

                //For Backend Validations
                if ($validationErrors !== true) {

                    return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
                } else {
                    $insertResult = $this->scrumPeopleMonthlyRatingObj->insertSelfRating($data);
                    if ($insertResult) {
                        return $this->response->setJSON([
                            'success' => true,
                            'message' => 'Self rating and feedback added successfully.'
                        ]);
                    }
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Failed to add self rating.'
                    ]);
                }

            }

            // Handle manager-rating: insert only if self-rating exists
            if ($data["ratingType"] === "manager") {
                if (empty($existingRow["self_monthly_rating_id"])) {
                    return $this->response->setJSON([
                        'success' => 'noSelfRate',
                        'message' => 'Self rating is mandatory before adding a manager rating.'
                    ]);
                }

                // Validate the goal data
                $validationErrors = $this->hasInvalidInput($this->scrumPeopleMonthlyRatingObj, $data);

                //For Backend Validations
                if ($validationErrors !== true) {

                    return $this->response->setJSON(['success' => false, 'message' => $validationErrors]);
                } else {
                    $updateResult = $this->scrumPeopleMonthlyRatingObj->updateManagerRating($data);
                    if ($updateResult) {
                        return $this->response->setJSON([
                            'success' => true,
                            'message' => 'Manager rating and feedback updated successfully.'
                        ]);
                    }
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Failed to update manager rating.'
                    ]);
                }
            }
        } else {
            $assignedGoals = $goals[0]['assigned_goals_count'];
            $reviewCompletedGoals = $goals[0]['review_completed_count'];

            return $this->response->setJSON([
                'success' => 'notCompleted',
                'message' => 'Goals not available for the specified month and year.',
                'data' => "Out of $assignedGoals assigned goals, $reviewCompletedGoals have been successfully completed."
            ]);
        }
    }

    /*
     * @category   GOAL BOARD DOWNLOD 
     * @author     SUDHARSANAN
     * @created    27 OCT 2024
     */


    // PDF Download Method
    public function goalBoardGeneratePdf()
    {
        $filtervalue = $this->request->getGet();
        // Set date range based on selected filter
        switch ($filtervalue['date']) {
            case 'thisMonth':
                $filtervalue['start_date'] = date('Y-m-01');
                $filtervalue['end_date'] = date('Y-m-t');
                break;
            case 'lastMonth':
                $filtervalue['start_date'] = date('Y-m-01', strtotime('first day of last month'));
                $filtervalue['end_date'] = date('Y-m-t', strtotime('last day of last month'));
                break;
        }

        $id = $this->employeeId; // Logged-in user ID
        $userService = service("users");
        $usersByParentId = $userService->getUsersByParentId($id);

        $employeeName = $_SESSION["first_name"];
        $tasklistWithGoal = [];
        $goallist = []; // Prepare final goal list 
        $taskList = []; // Prepare final task list grouped by goal name
        $allMonths = [];  // Add missing months
        $overallRatingName = "";
        $date_range = null;

        // Get employee name and fetch filtered goals
        foreach ($usersByParentId as $value) {
            if ($value["id"] == $filtervalue["manager"]) {
                $employeeName = $value["firstname"];
                break;
            }
        }
        $goallist = $this->goalsModelObj->goalboardfilter($filtervalue, $filtervalue["manager"]);
        $assignedGoalStatus = 0;
        $completedGoalsStatus = 40;

        // Fetch goal statuses
        $filtervalue["status"] = $filtervalue["status"] ?? 40; // Default to "review completed"
        $completedGoals = $this->goalsModelObj->goalsStatus($completedGoalsStatus, $filtervalue)[0]["count"];
        $assignedGoals = $this->goalsModelObj->goalsStatus($assignedGoalStatus, $filtervalue)[0]["count"];

        if (!empty($goallist)) {

            foreach ($goallist as $id) {
                $temp_tasklist = $this->goalsModelObj->taskDetails($id["goal_id"]);

                if ($temp_tasklist) {
                    $tasklistWithGoal[] = $temp_tasklist[0];
                }
            }
            foreach ($tasklistWithGoal as $entry) {
                $tasks = array_map(null, explode(', ', $entry['task_names']), explode(', ', $entry['start_dates']), explode(', ', $entry['end_dates']), explode(', ', $entry['completed_percentages']), explode(', ', $entry['status_names']));

                $taskList[$entry['goal_name']] = array_map(function ($task) {
                    return [
                        'task_name' => $task[0],
                        'start_date' => $task[1],
                        'end_date' => $task[2],
                        'completed_percentage' => $task[3] . "%",
                        'status_name' => $task[4],
                    ];
                }, $tasks);
            }

            // Get start and end year/month for filtering
            $filtervalue['start_year'] = date('Y', strtotime($filtervalue['start_date']));
            $filtervalue['start_month'] = date('m', strtotime($filtervalue['start_date']));
            $filtervalue['end_year'] = date('Y', strtotime($filtervalue['end_date']));
            $filtervalue['end_month'] = date('m', strtotime($filtervalue['end_date']));

            $reviewCompletedId = 40;
            // Prepare monthly ratings
            $monthlyManagerRatings = $this->goalsModelObj->getMonthlyManagerRating($filtervalue, $reviewCompletedId);
            $ratings = $this->goalsModelObj->peopleRating();

            $ratingMap = array_column($ratings, 'people_rating', 'people_rating_no');
            $totalManagerRating = 0;

            $monthlyRatings = [];
            $goalCount = 0;
            // Format monthly ratings and calculate totals
            if (!empty($monthlyManagerRatings)) {
                foreach ($monthlyManagerRatings as $rating) {
                    $goalCount = $monthlyManagerRatings[0]['total_goals'];
                    $monthYear = strtoupper(date('M-Y', strtotime($rating['year'] . '-' . $rating['month'] . '-01')));
                    $rating['month_year'] = $monthYear;
                    $monthlyRatings[$monthYear] = $rating;
                    if (isset($rating["manager"])) {
                        $totalManagerRating += array_search($rating['manager'], array_column($ratings, 'people_rating')) + 1;
                    }
                }
            }

            for ($year = $filtervalue['start_year']; $year <= $filtervalue['end_year']; $year++) {
                $startMonth = ($year == $filtervalue['start_year']) ? $filtervalue['start_month'] : 1;
                $endMonth = ($year == $filtervalue['end_year']) ? $filtervalue['end_month'] : 12;

                for ($month = $startMonth; $month <= $endMonth; $month++) {
                    $monthStr = strtoupper(date('M-Y', strtotime($year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01')));
                    if (!empty($monthlyRatings[$monthStr])) {
                        // Use data from $monthlyRatings if available and `total_goals` is set
                        $allMonths[$monthStr] = [
                            'month_year' => $monthStr,
                            'self' => $monthlyRatings[$monthStr]['self'] ?? 'N/A',
                            'total_goals' => $monthlyRatings[$monthStr]['total_goals'],
                            'manager' => $monthlyRatings[$monthStr]['manager'] ?? 'N/A',
                            'year' => $year,
                            'month' => $month,
                        ];
                    } else {
                        // Default values when $monthlyRatings[$monthStr] is not set or empty
                        $allMonths[$monthStr] = [
                            'month_year' => $monthStr,
                            'self' => 'N/A',
                            'total_goals' => 0,
                            'manager' => 'N/A',
                            'year' => $year,
                            'month' => $month,
                        ];

                    }
                }
                $averagecount = 0;
                foreach ($monthlyManagerRatings as $average) {
                    if (isset($average["manager"])) {
                        $averagecount++;
                    }
                }

                if ($averagecount == 0) {
                    $overallRatingName = "N/A";
                } else {
                    // Calculate overall rating
                    $averageManagerRating = $totalManagerRating / $averagecount;
                    $overallRatingName = $ratingMap[round($averageManagerRating)];
                }
            }
        }

        // Format date range
        $startFormatted = date('M Y', strtotime($filtervalue['start_date']));
        $endFormatted = date('M Y', strtotime($filtervalue['end_date']));
        $date_range = $startFormatted === $endFormatted ? $startFormatted : "$startFormatted to $endFormatted";

        // Prepare data for view
        $data = [
            'goalsList' => $goallist,
            'EmployeeName' => $employeeName,
            'completedGoals' => $completedGoals,
            'assignedGoals' => $assignedGoals,
            'taskList' => $taskList,
            'yearlyManagerRatings' => array_values($allMonths),
            'overallRatingName' => $overallRatingName,
            'status' => $filtervalue["status"],
            'filteredDate' => $date_range,
        ];

        // Render PDF
        $html = view("goals/goalBoardDownload", ['data' => $data], ['saveData' => true]);
        $dompdf = new Dompdf(['defaultFont' => 'Arial', 'isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream("$employeeName Goals Report.pdf");
    }
}
