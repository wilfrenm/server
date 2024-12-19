<?php

/**
 * SLAPolicyController.php
 *
 * @category   Controller
 * @package    App\Controllers
 * @description Manages SLA policies
 * 
 * @purpose    This controller is responsible for handling all aspects of SLA policy management
 *            
 * @authors    Naveen Kumar
 * @created    2024-10-31
 */

namespace App\Controllers;

use Codeigniter\Controller;
use App\Models\slaPolicy\SLAPolicyModel;
use CodeIgniter\HTTP\Response;

class SLAPolicyController extends BaseController
{
    protected $SLAPolicyModel;

    public function __construct()
    {
        $this->SLAPolicyModel = model(\App\Models\slaPolicy\SLAPolicyModel::class);
    }

    /**
     * @author NaveenKumar
     * @return string
     * Purpose: The purpose of this function is to get the product,project,category and total count details and return it to the view
     */
    public function getPolicyList(): string
    {
        $breadcrumbs = [
            'Home' => ASSERT_PATH . 'dashboard/dashboardView',
            "SLA Policy" => ASSERT_PATH . 'slaPolicy/slaPolicyList'
        ];

        $dateils = [
            'project_name' => $this->SLAPolicyModel->getProjectDetails(),
            'product_name' => $this->SLAPolicyModel->getProductDetails(),
            'category_type' => $this->SLAPolicyModel->getPolicyCategory(),
            'totalCount' => $this->SLAPolicyModel->getPolicyCount()
        ];

        $data = (!empty($dateils)) ? $dateils : null;
        return $this->template_view('slaPolicy/SlaPolicyList', $data, 'SLA Policy', $breadcrumbs);
    }

    /**
     * @author NaveenKumar
     * @return Response
     * Purpose: The purpose of this function is to get the customer list based on parent id
     */
    public function getCustomerDetails(): Response
    {
        $getID = $this->request->getPost();
        $parentId = !empty($getID) ? $getID : null;
        $data = $this->SLAPolicyModel->getCustomerDetails($parentId);
        return $this->response->setJSON([
            'status' => 'success',
            'customers' => $data
        ]);
    }

    /**
     * @author NaveenKumar
     * @return Response
     * Purpose: The purpose of this function is to get filtered SLA policy data
     */
    public function getFilteredPolicyList(): Response
    {
        $filterData = $this->request->getJSON(true);
        $filter = isset($filterData['filter']) ? $filterData['filter'] : null;

        $data = $this->SLAPolicyModel->selectSlaPolicyList($filter);

        return $this->response->setJSON([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * @author NaveenKumar
     * @return Response
     * Purpose: The purpose of this function is to insert the SLA policy data
     */
    public function slaPolicyInsert(): Response
    {
        $insertData = $this->request->getpost();
        $insertDetails = !empty($insertData) ? $insertData : null;

        $checkValidations = $this->hasInvalidInput($this->SLAPolicyModel, $insertDetails);
        if ($checkValidations !== true) {
            return $this->response->setJSON([
                'status' => 'valid',
                'error' => $checkValidations
            ]);
        }
        $data = $this->SLAPolicyModel->slaPolicyInsert($insertDetails);
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * @author NaveenKumar
     * @return Response
     * Purpose: The purpose of this function is to get particular policy detail for update purpose
     */
    public function slaPolicyEdit($plcId): Response
    {
        $policyId = !empty($plcId) ? $plcId : null;
        $data[0] = $this->SLAPolicyModel->slaPolicyEdit($policyId);
        return $this->response->setJSON($data[0]);

    }

    /**
     * @author NaveenKumar
     * @return Response
     * Purpose: The purpose of this function is to update the SLA policy
     */
    public function slaPolicyUpdate($plcId): Response
    {
        $updateDetails = $this->request->getpost();
        $updateData = !empty($updateDetails) ? $updateDetails : null;

        $checkValidations = $this->hasInvalidInput($this->SLAPolicyModel, $updateData);
        if ($checkValidations !== true) {
            return $this->response->setJSON([
                'status' => 'valid',
                'error' => $checkValidations
            ]);
        }

        $data = $this->SLAPolicyModel->slaPolicyUpdate($plcId, $updateData);
        return $this->response->setJSON([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * @author NaveenKumar
     * @return Response
     * Purpose: The purpose of this function is to change the policy status
     */
    public function slaPolicyUpdateStatus($plcId, $status): Response
    {
        $data = $this->SLAPolicyModel->slaPolicyUpdateStatus($plcId, $status);
        return $this->response->setJSON($data);
    }

}

