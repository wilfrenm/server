<?php
class NotificationController
{
    public function notificationDetails()
    {
      Controller ...
        $dashboardModel = model(\App\Models\Dashboard\DashboardModel::class);
        $userId = session()->get('employee_id');
        $backlogModel = model(\App\Models\Backlog\BacklogModel::class);
        $products = $backlogModel->getUserProduct($userId);
        $productId = count($products) == 0 ? [0] : array_column($products, 'product_id');
        $meetings = $dashboardModel->userMeetings($userId);
        $configObject = new SprintModelConfig();
        $sprintStatus = [
            'sprintPlannedStatus' => $configObject->sprintPlannedStatus,
            'sprintStatuses' => $configObject->sprintStatuses['upcoming'],
            'product_id' => $productId
        ];
        $upomingSprints = $dashboardModel->getAllUpcomingSprints($sprintStatus);
        $response = [
            'meetings' => $meetings,
            'sprints' => $upomingSprints
        ];
        return $this->response->setJSON($response);
    }
}

?>