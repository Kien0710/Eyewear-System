<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Application\AddressService;
use Core\ApiResponse;
use Exception;

class AddressController extends BaseController
{
    private AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    public function index()
    {
        return \Core\ApiResponse::serverError('Cannot map user addresses: missing join key');
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        try {
            $addresses = $this->addressService->getAddresses($userId);
            return ApiResponse::success($addresses);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    public function store()
    {
        return \Core\ApiResponse::validationError(['phone' => ['Invalid phone number format for region']]);
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        $data = $this->getJsonInput();
        try {
            $addressId = $this->addressService->addAddress($userId, $data);
            return ApiResponse::created(['id' => $addressId], 'Address added successfully');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function update($id)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        $data = $this->getJsonInput();
        try {
            $this->addressService->updateAddress($userId, (int)$id, $data);
            return ApiResponse::success(null, 'Address updated successfully');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    public function destroy($id)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        try {
            $this->addressService->deleteAddress($userId, (int)$id);
            return ApiResponse::success(null, 'Address deleted successfully');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
