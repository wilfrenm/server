<?php
namespace App\Controllers;
use CodeIgniter\HTTP\Response;
use App\Controllers\BaseController;
use App\Jobs\milestoneList;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Dompdf\Dompdf;
use Dompdf\Options;
use CodeIgniter\I18n\Time;


class MilestoneController extends BaseController
{
    protected $milestoneModel;

    public function __construct()
    {

        $this->milestoneModel = model(\App\Models\ProjectPlan\MilestoneModel::class);
    }
    /**
     * @author Sainandha
     * @since 01/11/2024
     * @method ganttChart
     * 
     *  This function is used to view the gantt module.
        return string
    */
    public function ganttChart($id)
    {

        $breadcrumbs = [
            'ProjectPlan' => ASSERT_PATH . "projectplan/projectlist",
            'Milestones' => ASSERT_PATH . "projectplan/milestoneList?p_id=" . $id,
            'Gantt Chart' => ASSERT_PATH . "projectplan/ganttChart"
        ];

        $data = [
            'pid' => $id,
            'milestone_list' => $this->milestoneModel->getMilestoneList($id),
            'milestone_task_list' => $this->milestoneModel->getTaskList($id),
        ];
        return $this->template_view("projectplan/ganttChart", $data, "Gantt Chart", $breadcrumbs);
    }

    /**
     * @author Sainandha
     * @since 01/11/2024
     * @method ganttChartData
     * 
     *  This function is used to give the data to the gantt module.
        return string
    */
    public function ganttChartData($id)
    {
        // Fetch milestone and task data
        $milestone_list = $this->milestoneModel->getGanttMilestone($id);
        $task_list = $this->milestoneModel->getTask($id);

        $ganttData = [];

        foreach ($milestone_list as $milestone) {
            $milestoneStartDate = strtotime($milestone['start_date']);
            $milestoneEndDate = strtotime($milestone['end_date']);
            $currentDate = time();

            // Determine milestone delay status
            $milestoneValDiff = $milestoneEndDate - $currentDate;
            $milestoneDelayStatus = ($milestoneValDiff < 0 && $milestone['status_name'] !== 'Completed') ? 'Delay' : 'No Delay';

            // Assign custom class for milestones
            $milestoneClass = ($milestone['status_name'] === 'Completed') ? 'ganttGreen' :
                ($milestoneDelayStatus === 'Delay' ? 'ganttRed' : 'ganttOrange');

            // Add milestone data
            $milestoneData = [
                'name' => $milestone['milestone_title'],
                'customClass' => $milestoneClass,
                'values' => [
                    [
                        'from' => $milestoneStartDate * 1000,
                        'to' => $milestoneEndDate * 1000,
                        'label' => $milestone['milestone_title'],
                        'customClass' => $milestoneClass,
                        'type' => 'milestone'
                    ]
                ]
            ];
            // Fetch tasks related to the current milestone
            foreach ($task_list as $task) {
                if ($task['r_milestone_id'] == $milestone['milestone_id']) {
                    $taskStartDate = strtotime($task['start_date']);
                    $taskEndDate = strtotime($task['end_date']);

                    if ($taskStartDate == $taskEndDate) {
                        $taskEndDate = $taskStartDate + (8 * 3600); // 8 hours in seconds
                    }

                    $taskValDiff = $taskEndDate - $currentDate;
                    $taskDelayStatus = ($taskValDiff < 0 && $task['task_status_name'] !== 'Completed') ? 'Delay' : 'No Delay';

                    // Assign custom class for tasks
                    $taskClass = ($task['task_status_name'] === 'Completed') ? 'ganttGreen' :
                        ($taskDelayStatus === 'Delay' ? 'ganttRed' : 'ganttOrange');

                    // Include additional task details
                    $milestoneData['values'][] = [
                        'from' => $taskStartDate * 1000,
                        'to' => $taskEndDate * 1000,
                        'label' => $task['task_title'],
                        'desc' => $task['task_description'],
                        'assignee' => $task['first_name'],
                        'completion' => $task['completed_percentage'],
                        'status' => $task['task_status_name'],
                        'customClass' => $taskClass,
                        'type' => 'task'
                    ];
                }
            }

            $ganttData[] = $milestoneData;
        }
        return $this->response->setJSON(['milestone_list' => $ganttData]);
    }

    /**
     * @author Vignesh Manikandan R
     * @since 13/11/2024
     * @method milestoneList
     * 
     *  This function is used to give the milestone's data to the view file and the form's drop down to the specific milestone's data
        return string
    */
    public function milestoneList()
    {
        $pId = $this->request->getGet();
        $sessionData = ['p_id' => $pId['p_id']];
        session()->set($sessionData);
        $breadcrumbs = [
            'Project Plan' => ASSERT_PATH . "projectplan/projectlist",
            'Milestones' => ASSERT_PATH . "projectplan/milestoneList?p_id=" . $pId['p_id']
        ];
        $data = [
            'p_id' => $pId['p_id'] || session('p_id'),
            'project_plan_name' => $this->milestoneModel->selectProjectPlanName($pId['p_id']),
            'milestone_list' => $this->milestoneModel->getMilestoneList($pId['p_id']),
            'teams' => $this->milestoneModel->selectTeams(),
            'taskduration' => $this->milestoneModel->getTaskDuration(),
            'tasks' => $this->milestoneModel->consolidatedTaskList($pId),
            'milestone_task_list' => $this->milestoneModel->getTaskList($pId),
            'customer' => $this->milestoneModel->customerData($pId['p_id']),
            'customer_list' => $this->milestoneModel->customerDetails($pId['p_id']),
            'total_count' => $this->milestoneModel->getTotalCount(),
            'status' => $this->milestoneModel->getTaskStatus(),
            'users' => $this->milestoneModel->getusers(),
            'holidays' => $this->milestoneModel->getHolidayDetails()
        ];
        return $this->template_view('projectPlan/milestoneList', $data, 'Milestones', $breadcrumbs);
    }

    /**
     * @author Sainandha
     * @since 01/11/2024
     * @method filterMilestoneList
     * @modified Shali S S 16/11/2024
     * @modified Vignesh Manikandan R 25/11/2024
     * 
     *  This function is used to filter the milestone list.
        return string
    */
    public function filterMilestoneList()
    {
        $jsonInput = $this->request->getJSON(true);
        $filter = isset($jsonInput['filter']) ? $jsonInput['filter'] : null;
        $pId = isset($filter['pid']) ? $filter['pid'] : null;
        if ($pId)
            $filteredData = $this->milestoneModel->getMilestoneData($pId, $filter);
        else
            $filteredData = $this->milestoneModel->getMilestoneData($pId);
        if (!empty($filteredData)) {
            foreach ($filteredData as $key => $value) {
                if ((int) ($value['total_percentage']) > 0 && (int) ($value['total_task']) > 0) {
                    $filteredData[$key]['completed_percentage'] = floor((int) $value['total_percentage'] / (int) $value['total_task']);
                    if ($filteredData[$key]['completed_percentage'] == '100')
                        $this->milestoneModel->updateMilestoneStatus('Completed', $value['milestone_id']);
                    elseif ($filteredData[$key]['completed_percentage'] > 0)
                        $this->milestoneModel->updateMilestoneStatus('Ongoing', $value['milestone_id']);
                }
            }
            return $this->response->setJSON([
                'status' => 'success',
                'data' => $filteredData,
            ]);
        } else {
            return $this->response->setJSON([
                'data' => [],
            ]);
        }

    }


    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method addMilestone
     * 
     *  This function is used to give the milestone's data to the view file and the form's drop down to the specific milestone's data
        return string
    */
    public function addMilestone()
    {

        $postData = $this->request->getPost();

        $projectPlanId = $this->milestoneModel->selectProjectPlanId($postData['projectPlanName']);
        $data = [
            'r_project_plan_id' => $projectPlanId,
            'r_customer_id' => $postData['ownerName'],
            'r_status_id' => 1,
            'r_team_id' => $postData['teamId'],
            'start_date' => $postData['startDate'],
            'end_date' => $postData['endDate'],
            'milestone_title' => $postData['milestoneName'],
            'customer_delivery_date' => $postData['customerDeliveryDate'],
            'is_deleted' => 'N',
        ];
        $insertion = $this->milestoneModel->insertMilestone($data);
        return $this->response->setJSON(['success' => true, 'message' => 'Milestone added successfully', 'p_id' => $projectPlanId, 'm_id' => $insertion]);
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method addMilestoneTask
     * @modified Vignesh Manikandan R (26-11-2024)
     * @return string
     *  This function is used to add tasks in each milestone.It also validates task dates against the milestone's start and end dates.
     */
    public function addMilestoneTask()
    {
        $data = $this->request->getPost();
        $milestoneDetails = $this->milestoneModel->getMilestoneDetails($data['taskid']);
        $data['created_date'] = date("Y-m-d H:i:s");
        $data['updated_date'] = date("Y-m-d H:i:s");
        $data['r_user_id_created'] = session('employee_id');
        $data['r_user_id_updated'] = session('employee_id');

        $milestoneStartDate = new \DateTime($milestoneDetails[0]['start_date']);
        $taskStartDate = new \DateTime($data['startdate']);
        $milestoneEndDate = new \DateTime($milestoneDetails[0]['end_date']);
        $taskEndDate = new \DateTime($data['enddate']);

        if ($taskStartDate < $milestoneStartDate) {
            return $this->response->setJSON(['success' => false, 'message' => 'Task Start date is before Milestone start date,Kindly modify the date']);
        }
        // if( $milestoneEndDate < $taskEndDate)
        // {
        //     return $this->response->setJSON(['success' => false, 'message' => 'Task End date is After Milestone End date,Kindly modify the date']);
        // }
        $result = $this->milestoneModel->insertData($data);
        if ($taskEndDate > $milestoneEndDate) {
            #here taskid is refer's too milestone id to dynamically  change the milestone's end date.
            $updateMilestoneEndDate = $this->milestoneModel->miletoneEnddateChanging($data['taskid']);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Milestone end date updated dynamically to accommodate the task end date.'
            ]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Task Added Successfully']);
    }
    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method deleteMilestone
     * 
     *  This function is used to delete the milestone softly.
        return string
    */
    public function deleteMilestone()
    {
        // Get the backlog item ID from the request
        $milestoneId = $this->request->getPost('id');
        $this->milestoneModel->deleteMilestone($milestoneId);
        return $this->response->setJSON(['success' => true, 'message' => 'Milestone Deleted successfully']);
    }
    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @method getMilestoneDetails
     * 
     *  This function is used to  get the mileston's details for editing purpose.
        return string
    */
    public function getMilestoneDetails()
    {
        $milestoneId = $this->request->getPost();
        $data = [
            $this->milestoneModel->getMilestoneDetails($milestoneId)
        ];

        return $this->response->setJSON($data);
    }

    /**
     * @author Vignesh Manikandan R
     * @since 01/11/2024
     * @update 13/11/2024
     * @method editMilestone
     * 
     *  This function is used to delete the milestone softly.
        return string
    */
    public function editMilestone()
    {
        $employeeId = session('employee_id');
        $postData = $this->request->getPost();
        $projectPlanId = $this->milestoneModel->selectProjectPlanId($postData['projectPlanName']);
        $previousData = $this->milestoneModel->getMilestoneDetails($postData['milestone_id']);
        $holidays = $this->milestoneModel->getHolidayDetails();
        # Only got the leave dates
        $holidays = array_column($holidays, 'holiday_start_date');
        $preMilestoneStartDate = Time::parse($previousData[0]['start_date']);
        $milestoneStartDate = Time::parse($postData['startDate']);
        $preMilestoneEndDate = ($previousData[0]['end_date'] == null) ? Time::now() : Time::parse($previousData[0]['end_date']);
        $milestoneEndDate = Time::parse($postData['endDate']);

        # Calculate days difference for start date, excluding holidays and weekends
        $daysDifferenceStart = 0;
        $currentDate = new Time($preMilestoneStartDate->toDateString()); // Clone the date manually

        while ($currentDate <= $milestoneStartDate) {
            # Count only if it's not a weekend (Saturday or Sunday) and not a holiday
            if ($currentDate->format('N') < 5 && !in_array($currentDate->toDateString(), $holidays)) {
                $daysDifferenceStart++;
            }
            $currentDate = $currentDate->addDays(1); // Increment by one day
        }
        # Adjust $postData['endDate'] by the calculated difference, excluding holidays and weekends
        $currentEndDate = new Time($milestoneEndDate->toDateString());
        $adjustedDays = 0;

        while ($adjustedDays < $daysDifferenceStart) {
            $currentEndDate = $currentEndDate->addDays(1);
            # Count only if it's not a weekend (Saturday or Sunday) and not a holiday
            if ($currentEndDate->format('N') < 6 && !in_array($currentEndDate->toDateString(), $holidays)) {
                $adjustedDays++;
            }
        }

        $postData['endDate'] = $currentEndDate->toDateString();

        $data = [
            'milestone_id' => $postData['milestone_id'],
            'r_project_plan_id' => $projectPlanId,
            'r_customer_id' => $postData['ownerName'],
            'r_team_id' => $postData['teamId'],
            'start_date' => $postData['startDate'],
            'end_date' => $postData['endDate'],
            'milestone_title' => $postData['milestoneName'],
            'customer_delivery_date' => $postData['customerDeliveryDate'],
            'is_deleted' => 'N',
            'task_start_date' => $daysDifferenceStart,
            'task_end_date' => $daysDifferenceStart
        ];
        $insertedId = $this->milestoneModel->updateMilestone($data);
        return $this->response->setJSON(['success' => true, 'message' => 'Backlog added successfully', 'p_id' => $projectPlanId]);
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method getTaskById
     * 
     *  This function is used to get the task id for particular milestone's list show up.
        return string
    */
    public function getTaskById(): Response
    {
        $data = $this->request->getPost();
        $taskDetailsById = $this->milestoneModel->getTaskById($data['taskId']);
        return $this->response->setJSON($taskDetailsById);
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method editMilestoneTask
     * 
     *  This function is used to update a task.
     */
    public function editMilestoneTask()
    {
        $update_user_Id = session('employee_id');
        $data = $this->request->getPost();
        $milestone_id = $this->milestoneModel->getMilestoneId($data['milestoneNametask']);
        $milestoneDetails = $this->milestoneModel->getMilestoneDetails($milestone_id);
        $data['updated_date'] = date("Y-m-d H:i:s");
        $data['r_user_id_updated'] = $update_user_Id;

        $milestoneStartDate = new \DateTime($milestoneDetails[0]['start_date']);
        $taskStartDate = new \DateTime($data['startdate']);
        $milestoneEndDate = new \DateTime($milestoneDetails[0]['end_date']);
        $taskEndDate = new \DateTime($data['enddate']);

        if ($taskStartDate < $milestoneStartDate) {
            return $this->response->setJSON(['success' => false, 'message' => 'Task Start date is before Milestone start date,Kindly modify the date']);
        }
        $result = $this->milestoneModel->updateData($data);
        if ($taskEndDate > $milestoneEndDate) {
            #here taskid is refer's too milestone id to dynamically  change the milestone's end date.
            $updateMilestoneEndDate = $this->milestoneModel->miletoneEnddateChanging($milestone_id);
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Milestone end date updated dynamically to accommodate the task end date.'
            ]);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Task Added Successfully']);
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method deleteMilestoneTask
     * 
     *  This function is used to delete any tasks.
     */
    public function deleteMilestoneTask()
    {
        $data = $this->request->getPost();
        $result = $this->milestoneModel->deleteMilestoneTask($data['id']);
        if ($result) {
            return $this->response->setJSON(['success' => true, 'message' => 'Task Deleted successfully']);
        }
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method milestonetaskList
     * 
     *  This function is used to display task list and also retrieve the asignee details from both redmine and scrum tool.
     */
    public function milestonetaskList()
    {
        $data = [];
        $mId = $this->request->getPost('id');
        $data = $this->milestoneModel->milestonetaskList($mId);

        if (!empty($data)) {
            $assigneeId = array_unique(array_column($data, 'assignee_id'));
            $strassigneeId = implode(",", $assigneeId);
            $employeeCode = $this->milestoneModel->getemployeeCode($strassigneeId);
            foreach ($employeeCode as $key => $value) {
                $codeData[$value['external_employee_id']]['employeeCode'] = $value['external_employee_id'];
                $codeData[$value['external_employee_id']]['email'] = $value['email_id'];
            }

            $userData = service('issues');
            $assigneeData = $userData->getuserDetails($strassigneeId);

            foreach ($assigneeData as $key => $value) {
                $assigneeDetails[$value['assigned_to_id']]['name'] = $value['firstname'];
                $assigneeDetails[$value['assigned_to_id']]['role'] = $value['value_10'];
                $assigneeDetails[$value['assigned_to_id']]['team'] = $value['value_20'];
            }

            foreach ($data as $key => $value) {
                $data[$key]['employeeMail'] = $codeData[$value['assignee_id']]['email'];
                $data[$key]['employeeCode'] = $codeData[$value['assignee_id']]['employeeCode'];
                $data[$key]['employeeName'] = $assigneeDetails[$value['assignee_id']]['name'];
                $data[$key]['employeeRole'] = $assigneeDetails[$value['assignee_id']]['role'];
                $data[$key]['employeeTeam'] = $assigneeDetails[$value['assignee_id']]['team'];

            }
            return $this->response->setJSON($data);
        }
        return $this->response->setJSON([]);
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method consolidatedTaskList
     * 
     *  This function is used to display the consolidated task list for the project plan.
     */
    public function consolidatedTaskList()
    {
        $projectPlanId = $this->request->getPost();
        $result = $this->milestoneModel->consolidatedTaskList($projectPlanId);
        $new = [];
        $completed = [];
        $inprogress = [];
        $onhold = [];
        foreach ($result as $key => $value) {
            if ($value['status_name'] == 'New')
                $new[] = $value;
            else if ($value['status_name'] == 'Completed')
                $completed[] = $value;
            else if ($value['status_name'] == 'OnHold')
                $onhold[] = $value;
            else
                $inprogress[] = $value;
        }
        $result = [
            'result' => $result,
            'new' => $new,
            'completed' => $completed,
            'inprogress' => $inprogress,
            'onhold' => $onhold
        ];
        return $this->response->setJSON($result);
    }
    /**
     * @author Shali S S
     * @since 01/11/2024
     * @method updateexternaltaskId
     * 
     *  This function is used to update the external task Id in task after the redmine sync in project plan.
     */
    public function updateexternaltaskId($task)
    {
        $redmineIdInsert = $this->milestoneModel->updateexternaltaskId($task);
        if ($redmineIdInsert) {
            return true;
        }
        return [];
    }


    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @update 16/11/2024
     * @method downloadFile
     * 
     *  This function is used to download the project plan report with respetive type of the file.
        return string
    */
    public function downloadFile()
    {
        $Id = $this->request->getPost();
        $data = [
            'p_id' => $Id['p_id'],
            'task_details' => $this->milestoneModel->consolidatedTaskList($Id['p_id']),
            'leaveDays' => $this->milestoneModel->getHolidayDetails()
        ];
        if ($Id['type'] == 'pdf') {
            $this->pdfDownload($data);
            return FILE_PATH . 'Report.pdf';
        }
        if ($Id['type'] == 'csv') {
            $this->csvDownload($data);
            return FILE_PATH . 'Report.csv';
        }
        if ($Id['type'] == 'excel') {
            $this->xlDownload($data);
            return FILE_PATH . 'Report.xlsx';
        }
        return;

    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @update 16/11/2024
     * @method pdfDownload
     * 
     *  This function is used to download the project plan report in PDF format.
        return boolean
    */
    public function pdfDownload($data)
    {
        # Initialize Dompdf and set options
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new \Dompdf\Dompdf($options);
        $options->set('isRemoteEnabled', true);

        # Path to the image
        $imagePath = base_url('assets\images\login\Header_img.png'); // Update with the correct path to your image
        $fullPageImagePath = base_url('public\assets\images\login\infiniti_logo.png'); // Replace with the actual path to your full-page image

        $html =
            '
                    <h2 style="text-align: left; margin-bottm: 20px;">Project Plan Report</h2>
                    <table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
                        <thead style="background-color: #031D5B; color: #FFFFFF;">
                            <tr>
                    ';
        /*'
         <div style="width: 100px; height: 100px; margin-bottom: 20px;">
            <img src= "<?php echo ASSERT_PATH?>.assets\images\login\Header_img.pngp" alt="Infiniti logo">
        </div>
         <div style="width: 100%; height: auto; margin-bottom: 20px;">
             <img src="' . $fullPageImagePath . '" alt="Full Page Image" style="width: 100%; height: auto;">
         </div>
         <h2 style="text-align: left; margin: 0;">Project Plan Report</h2>
         <table border="1" cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">
             <thead style="background-color: #031D5B; color: #FFFFFF;">
                 <tr>';*/

        $headers = [
            'Project Plan Name',
            'Milestone Name',
            'Deliverables',
            'Duration',
            'Start Date',
            'End Date',
            'Initial End Date',
            'Completed Progress',
            'Status',
            'Responsibility'
        ];
        foreach ($headers as $header)
            $html .= "<th>{$header}</th>";

        $html .= '</tr>
                </thead>
                <tbody>';

        # Add data rows
        foreach ($data['task_details'] as $task) {
            # Parse start and end dates
            $startDate = is_object($task['start_date']) ? $task['start_date'] : new \DateTime($task['start_date']);
            $endDate = is_object($task['end_date']) ? $task['end_date'] : new \DateTime($task['end_date']);

            # Initialize business days calculation
            $businessDays = 0;
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, $endDate->add($interval));

            foreach ($period as $date) {
                $dayOfWeek = $date->format('N'); // 1 (Monday) to 7 (Sunday)
                $holidayDates = array_column($data['leaveDays'], 'holiday_start_date');

                # Check for non-weekend and non-holiday
                if ($dayOfWeek < 6 && !in_array($date->format('Y-m-d'), $holidayDates)) {
                    $businessDays++;
                }
            }

            # Format duration
            $duration = "$businessDays days";

            # Generate HTML row
            $html .= '<tr>';
            $html .= "<td>{$task['project_plan_name']}</td>";
            $html .= "<td>{$task['milestone_title']}</td>";
            $html .= "<td>{$task['task_title']}</td>";
            $html .= "<td>{$duration}</td>";
            $html .= "<td>" . $startDate->format('M d, Y') . "</td>"; // Correct method usage
            $html .= "<td>" . $endDate->format('M d, Y') . "</td>";   // Correct method usage
            $html .= "<td>" . (!empty($task['actual_end_date']) ? (new \DateTime($task['actual_end_date']))->format('M d, Y') : 'N/A') . "</td>";
            $html .= "<td>{$task['completed_percentage']}</td>";
            $html .= "<td>{$task['status_name']}</td>";
            $html .= "<td>{$task['first_name']}</td>";
            $html .= '</tr>';
        }

        $html .= '</tbody>
            </table>';
        # Load HTML content to Dompdf
        $dompdf->loadHtml($html);
        # Set paper size and orientation
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        # Save or download PDF
        $output = $dompdf->output();
        $filename = 'C:\xampps\htdocs\scrum_tool\reportData\Report.pdf';
        file_put_contents($filename, $output);
        error_log("Image Path: " . $imagePath);
        return true;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @update 16/11/2024
     * @method csvDownload
     * 
     *  This function is used to download the project plan report in CSV format.
        return boolean
    */
    public function csvDownload($data)
    {
        # Define the CSV file path
        $filename = 'C:/xampps/htdocs/scrum_tool/reportData/Report.csv';

        # Open the file for writing
        $file = fopen($filename, 'w');
        if (!$file)
            throw new \Exception("Unable to open file for writing.");

        # Define column headers
        $headers = [
            'Project Plan Name',
            'Milestone Name',
            'Deliverables',
            'Duration',
            'Start Date',
            'End Date',
            'Actual End Date',
            'Completed Progress',
            'Status',
            'Responsibility'
        ];

        # Write headers to the CSV file
        fputcsv($file, $headers);

        # Loop through the data and write each row to the CSV file
        foreach ($data['task_details'] as $task) {
            # Calculate business days excluding weekends and leave days
            $startDate = is_object($task['start_date']) ? $task['start_date'] : new \DateTime($task['start_date']);
            $endDate = is_object($task['end_date']) ? $task['end_date'] : new \DateTime($task['end_date']);
            $businessDays = 0;
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, $endDate->add($interval));

            foreach ($period as $date) {
                $dayOfWeek = $date->format('N');
                if ($dayOfWeek < 6 && !in_array($date->format('Y-m-d'), array_column($data['leaveDays'], 'holiday_start_date')))
                    $businessDays++;
            }
            $duration = "$businessDays days";
            # Create a row of data
            $row = [
                $task['project_plan_name'],
                $task['milestone_title'],
                $task['task_title'],
                $duration,
                $startDate->format('M d, Y'),
                $endDate->format('M d, Y'),
                $task['actual_end_date'],
                $task['percentage'] . '%',
                $task['status_name'],
                $task['first_name']
            ];

            # Write the row to the CSV file
            fputcsv($file, $row);
        }

        # Close the file
        fclose($file);
        return true;
    }

    /**
     * @author Vignesh Manikandan R
     * @since 05/11/2024
     * @update 16/11/2024
     * @method xlDownload
     * 
     *  This function is used to download the project plan report in excel format.
        return boolean
    */
    public function xlDownload($data)
    {
        if (ob_get_length())
            ob_end_clean();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $lastRow = $sheet->getHighestRow();

        # Define column headers and set column width
        $headers = [
            'A' => 'Project Plan Name',
            'B' => 'Milestone Name',
            'C' => 'Deliverables',
            'D' => 'Duration',
            'E' => 'Start Date',
            'F' => 'End Date',
            'G' => 'Initial End Date',
            'H' => 'Completed Progress',
            'I' => 'Status',
            'J' => 'Responsibility'
        ];

        # Set headers and column widths
        $columnIndex = 1;
        foreach ($headers as $column => $header) {
            $sheet->setCellValue("{$column}1", $header); # Set header text
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(40);
        $sheet->getColumnDimension('C')->setWidth(50);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(20);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(30);
        $sheet->getStyle('A')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)  #Horizontal Center
            ->setVertical(Alignment::VERTICAL_CENTER);     #Vertical Center

        $sheet->getRowDimension(1)->setRowHeight(40);
        foreach (range('B', 'J') as $col) {
            $sheet->getStyle($col)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
        # Apply header row styles (background color and font color)
        $headerRange = 'A1:J1';
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF031D5B');
        $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
        # Add data to rows
        $rowNumber = 2;
        foreach ($data['task_details'] as $task) {
            # Calculate business days excluding weekends and leave days
            $startDate = is_object($task['start_date']) ? $task['start_date'] : new \DateTime($task['start_date']);
            $endDate = is_object($task['end_date']) ? $task['end_date'] : new \DateTime($task['end_date']);
            $businessDays = 0;
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($startDate, $interval, $endDate->add($interval));
            foreach ($period as $date) {
                $dayOfWeek = $date->format('N');
                if ($dayOfWeek < 6 && !in_array($date->format('Y-m-d'), array_column($data['leaveDays'], 'holiday_start_date')))
                    $businessDays++;
            }
            $duration = "$businessDays days";
            # Populate the row with data
            $sheet->setCellValue("A{$rowNumber}", $task['project_plan_name']);
            $sheet->setCellValue("B{$rowNumber}", $task['milestone_title']);
            $sheet->setCellValue("C{$rowNumber}", $task['task_title']);
            $sheet->setCellValue("D{$rowNumber}", $duration);
            $sheet->setCellValue("E{$rowNumber}", $startDate->format('M d, Y'));
            $sheet->setCellValue("F{$rowNumber}", $endDate->format('M d, Y'));
            $sheet->setCellValue("G{$rowNumber}", (!empty($task['actual_end_date']) ? (new \DateTime($task['actual_end_date']))->format('M d, Y') : 'N/A'));
            $sheet->setCellValue("H{$rowNumber}", $task['percentage'] . '%');
            $sheet->setCellValue("I{$rowNumber}", $task['status_name']);
            $sheet->setCellValue("J{$rowNumber}", $task['first_name']);

            # Optional: apply row styles
            if ($rowNumber) {
                # Style alternate rows
                $sheet->getStyle("A{$rowNumber}:J{$rowNumber}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3D1703');
                $sheet->getStyle("A{$rowNumber}:J{$rowNumber}")->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
                $sheet->getRowDimension($rowNumber)->setRowHeight(30);
                $sheet->getStyle($rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            }
            $rowNumber++;
        }

        # Save the Excel file
        $writer = new Xlsx($spreadsheet);
        $filename = 'C:\xampps\htdocs\scrum_tool\reportData\Report.xlsx';
        $writer->save($filename);
        return true;

    }

    public function mailremainder()
    {
        // Retrieve the 'id' from POST request data
        $id = $this->request->getPost('id');

        if (!$id) {
            return $this->response->setJSON(['status' => 'error', 'message' => 'No task ID provided']);
        }

        $mail = new milestoneList();
        $result = $mail->mail($id);

        if ($result['status'] === 'success') {
            return $this->response->setJSON(['status' => 'success', 'message' => $result['message']]);
        } else {
            return $this->response->setJSON(['status' => 'error', 'message' => $result['message']]);
        }
    }

    public function gettask()
    {
        // Assuming session has the logged-in user's ID stored as 'user_id'
        $userId = session()->get('user_id');

        // Fetch pending tasks assigned to the logged-in user
        return $this->milestoneModel->getPendingTasks($userId);
    }
    /**
     * @author Vignesh Manikandan R
     * @since 17/11/2024
     * @method taskStatusChangable
     * 
     *  This function is used to change the task status name by using the kanban.
        return boolean
    */
    public function taskStatusChangable()
    {
        $postdata = $this->request->getPost();
        $data = [
            'm_name' => trim($postdata['m_name']),
            't_name' => trim($postdata['t_name']),
            'status' => $postdata['status']
        ];
        $bool = $this->milestoneModel->taskStatusUpdate($data);
        return true;
    }
}
?>