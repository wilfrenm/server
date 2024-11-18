<?php
 public function refinement(): string
    {
        // Retrieve the product ID from the request's query parameters
        $pId = $this->request->getGet('pid');

        // Define breadcrumb navigation for the page
        $breadcrumbs = [
            'Products' => ASSERT_PATH . 'backlog/productbacklogs',
            'Backlog items' => ASSERT_PATH . 'backlog/backlogitems?pid=' . $pId,
            'Backlog refinement' => '', // Current page
        ];

        // Gather data required for the view
        $data = [
            'p_id' => $pId, // Product ID
            'productName' => $this->backlogModel->getProductDetails($pId), // Product details
            'backlogItemType' => $this->backlogModel->getBacklogDetails(['refinement' => 1, 'pid' => $pId]), // Backlog items for refinement
            'backlog_item_status' => $this->backlogModel->getStatus(BACKLOG_MODULE), // Statuses for backlog items
            'backlog_item_customer' => $this->backlogItemModel->getBacklogItemCustomer(), // Backlog item customers
            'tracker' => $this->taskModel->getTrackers(), // Trackers for tasks
        ];

        // Render the view with the provided data and breadcrumb navigation
        return $this->template_view('backlog/refinement', $data, 'Backlog Refinement', $breadcrumbs);
    }
?>