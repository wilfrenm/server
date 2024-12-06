<?php
/**
 * ReportController.php
 *
 * @category   Controller
 * @author     Rameshkrishnan.M, Bharath.J.R, Mahasri.K, Kavitha.L
 * @created    26-OCT-2024
 * @purpose    // Manages the generation and display of reports, including data retrieval and formatting. Handles user interactions like filtering, exporting report data. Handles email functionality to share reports.
 */
namespace App\Controllers;
use App\Controllers\BaseController;
use App\Models\Redmine\RedmineModel;
use App\Helpers\CustomHelpers as DateHelper;
use App\Jobs\EmailJob;

#class of report controller
class ReportController extends BaseController
{
    #variable declared as global 
    protected $redmineReportModel;
    #inside the constructor the model file object is created using global variable
    public function __construct()
    {
        $modelFilePath = APPPATH . 'Models/Redmine/RedmineModel.php';
        // Check if the model file exists
        if (file_exists($modelFilePath)) {
            $this->redmineReportModel = new RedmineModel();
        } else {
            // Handle the case where the model file does not exist
            throw new \Exception("We are unable to process your request at the moment.");
        }
    }
    #function for redmine dashboard
    public function report()
    {
        #breadcrumbs for redirecting the page  
        $breadcrumbs =
            [
                'Home' => ASSERT_PATH . 'dashboard/dashboardView',
                'RedmineReport' => ASSERT_PATH . 'report/redmineReport'
            ];
        #this condition execute only when custom applies filter
        if ($this->request->isAJAX()) {
            #storing the request from ajax in variable
            $date = $this->request->getPost();
            #assigning the value from date vaiable to variables for specification 
            $fromDate = $date['fromDate'];
            $toDate = $date['toDate'];
            $team = $date['team'] ?? '';
            $id = session()->get('employee_id');
            $result = $this->getChartData($fromDate, $toDate, $id, $team);
            return $this->response->setJSON($result);
        }
        #this condition execute by default when applies opens dashboard 
        else {
            #the default dynamic date is assigning to fromdate and todate
            $toDate = date('Y-m-d');
            $fromDate = date('Y-m-d', strtotime('-1 month'));
            $team = '';
            $id = session()->get('employee_id');
            $result = $this->getChartData($fromDate, $toDate, $id, $team);
            $dropdownData = $this->redmineReportModel->dropdown();
            $data = array_merge($result, ['dropdownData' => $dropdownData]);
            return $this->template_view('report/redmineReport', $data, $title = "Redmine Report", $breadcrumbs);

        }
    }
    public function ChartData()
    {
        if ($this->request->isAJAX()) {
            // Get the data from the AJAX request
            $date = $this->request->getPost();

            // Extract parameters
            $fromDate = $date['fromDate'] ?? null;
            $toDate = $date['toDate'] ?? null;
            $dataType = $date['dataType'] ?? 'top';
            $chartIndex = $date['chartIndex'] ?? null; // Identifies which chart is requesting data
            $id = session()->get('employee_id');
            $team = $date['team'] ?? '';

            // Determine which model function to call based on chartIndex
            $result = [];
            try {
                switch ($chartIndex) {
                    case 0:
                        $result = $this->redmineReportModel->logHours($fromDate, $toDate, $id, $dataType, $team);
                        break;
                    case 2:
                        $result = $this->redmineReportModel->topProjects($fromDate, $toDate, $id, $dataType, $team);
                        break;
                    case 3:
                        $result = $this->redmineReportModel->topActivites($fromDate, $toDate, $id, $dataType, $team);
                        break;
                    // case 3:
                    //     $result = $this->redmineReportModel->logEmployeeEfficiency($fromDate, $toDate, $id, $dataType);
                    //     break;
                    default:
                        log_message('error', "Invalid chart index: {$chartIndex}");
                        return $this->response->setStatusCode(400)->setBody("Invalid chart index");
                }
            } catch (\Exception $e) {
                log_message('error', "Error fetching data for chart index {$chartIndex}: " . $e->getMessage());
                return $this->response->setStatusCode(500)->setBody("Error processing request");
            }

            // Return the result as JSON
            return $this->response->setJSON($result);
        } else {
            log_message('error', 'Non-AJAX request made to ChartData');
            return $this->response->setStatusCode(400)->setBody("Invalid request");
        }
    }

    public function getChartData($fromDate, $toDate, $id, $team)
    {
        $projectWiseCountData = $this->redmineReportModel->projectWiseUserCount($fromDate, $toDate, $id, $team);
        $projectTrackerData = $this->redmineReportModel->projectTracker($fromDate, $toDate, $id, $team);

        $result = [
            'overallcount' => $this->redmineReportModel->OverallCount($fromDate, $toDate),
            'logHours' => $this->redmineReportModel->logHours($fromDate, $toDate, $id, '', $team),
            'logPercentage' => $this->redmineReportModel->logPercentage($fromDate, $toDate, $id, $team),
            'topProjects' => $this->redmineReportModel->topProjects($fromDate, $toDate, $id, '', $team),
            'topActivities' => $this->redmineReportModel->topActivites($fromDate, $toDate, $id, '', $team),
            'timeSpendTask' => $this->redmineReportModel->mostTimeSpendIssues($fromDate, $toDate, $id, $team),
            'count' => $this->redmineReportModel->totalProjectCount($fromDate, $toDate, $id, $team),
            'unloggedEmp' => $this->redmineReportModel->unloggedEmployees($fromDate, $toDate, $id, $team),
            'projectWiseCountData' => $projectWiseCountData['pivotedData'],
            'uniqueTeams' => $projectWiseCountData['uniqueTeams'],
            'efficientRedmineUsers' => $this->redmineReportModel->efficientRedmineUsers($fromDate, $toDate, $id, $team),
            'pivotedData' => $projectTrackerData['pivotedData'],
            'uniqueTrackers' => $projectTrackerData['uniqueTrackers']
        ];

        return $result;
    }

    #this is an function used to execute mail automation 
    public function execute()
    {
        $job = new EmailJob();
        $job->execute();
    }
}
?>