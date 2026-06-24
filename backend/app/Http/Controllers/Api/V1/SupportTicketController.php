<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Application\SupportTicketService;
use Core\ApiResponse;
use Exception;

class SupportTicketController extends BaseController
{
    private SupportTicketService $supportService;

    public function __construct(SupportTicketService $supportService)
    {
        $this->supportService = $supportService;
    }

    /**
     * Get ticket list.
     */
    public function index()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        try {
            if ($this->hasPermission('contact_customer')) {
                $tickets = $this->supportService->getAllOpenTickets();
            } else {
                $tickets = $this->supportService->getUserTickets($userId);
            }
            return ApiResponse::success($tickets);
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Get ticket details.
     */
    public function show($id = null)
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        $id = $id ?? $this->query('id');
        if (!$id) {
            return ApiResponse::validationError('Ticket ID required');
        }

        try {
            $ticket = $this->supportService->getTicketDetails((int) $id);
            // Security check: Only staff or ticket owner can see details
            if (!$this->hasPermission('contact_customer') && $ticket['user_id'] != $userId) {
                return ApiResponse::forbidden();
            }
            return ApiResponse::success($ticket);
        } catch (Exception $e) {
            return ApiResponse::notFound($e->getMessage());
        }
    }

    /**
     * Create new ticket.
     */
    public function store()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return ApiResponse::unauthorized();
        }

        $input   = $this->getJsonInput();
        $subject = $input['subject'] ?? '';
        $message = $input['message'] ?? '';
        $orderId = $input['order_id'] ?? null;

        if (!$subject || !$message) {
            return ApiResponse::validationError('Subject and message are required');
        }

        try {
            $ticket = $this->supportService->createTicket($userId, $subject, $message, $orderId);
            return ApiResponse::created($ticket, 'Ticket created successfully');
        } catch (Exception $e) {
            return ApiResponse::serverError($e->getMessage());
        }
    }

    /**
     * Add reply to ticket.
     */
public function reply()
{
    $userId = $this->getUserId();
    if (!$userId) {
        return ApiResponse::unauthorized();
    }

    $input = $this->getJsonInput();
    
    // 🔍 Debug: Kiểm tra input
    error_log('Input nhận được: ' . json_encode($input));
    error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'không có'));
    
    $ticketId = $input['ticket_id'] ?? null;
    $message  = $input['message'] ?? '';

    // ⚠️ Validation chi tiết
    if (!$ticketId || !is_numeric($ticketId)) {
        return ApiResponse::validationError([
            'error' => 'ticket_id is required and must be a number',
            'received' => $ticketId,
            'input' => $input
        ]);
    }
    
    if (empty(trim($message))) {
        return ApiResponse::validationError([
            'error' => 'message is required and cannot be empty',
            'received' => $message
        ]);
    }

    try {
        $isStaff = $this->hasPermission('contact_customer');
        $reply = $this->supportService->addReply((int) $ticketId, $userId, $message, $isStaff);
        return ApiResponse::success($reply, 'Reply added successfully');
    } catch (Exception $e) {
        return ApiResponse::error($e->getMessage());
    }
}
    /**
     * Update ticket status (Staff only).
     */
    public function updateStatus()
    {

        $input    = $this->getJsonInput();
        $ticketId = $input['ticket_id'] ?? null;
        $status   = $input['status'] ?? null;

        $allowed = ['open', 'in_progress', 'resolved', 'closed'];
        if (!$ticketId || !$status || !in_array($status, $allowed)) {
            return ApiResponse::validationError('ticket_id and a valid status are required');
        }

        try {
            $ticket = $this->supportService->updateTicketStatus((int) $ticketId, $status);
            return ApiResponse::success($ticket, "Ticket status updated to '{$status}'");
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }

    /**
     * Delete ticket (Staff only).
     */
    public function delete()
    {

        $input    = $this->getJsonInput();
        $ticketId = $input['ticket_id'] ?? null;

        if (!$ticketId) {
            return ApiResponse::validationError('ticket_id is required');
        }

        try {
            $this->supportService->deleteTicket((int)$ticketId, true);
            return ApiResponse::success(null, 'Ticket deleted successfully');
        } catch (Exception $e) {
            return ApiResponse::error($e->getMessage());
        }
    }
}
