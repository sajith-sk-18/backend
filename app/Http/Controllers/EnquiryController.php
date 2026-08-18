<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Notification;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EnquiryController extends Controller
{
    /**
     * POST /api/enquiries  (public)
     * Anyone can submit an enquiry. Guests supply their own name/email;
     * logged-in customers have theirs auto-attached from the account.
     * Optional product_id ties the enquiry to a specific product page.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // All fields are required for an enquiry (name, email, phone, WhatsApp, message).
        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'name'       => 'required|string|max:120',
            'email'      => 'required|email|max:190',
            'phone'      => 'required|string|max:30',
            'whatsapp'   => 'required|string|max:30',
            'message'    => 'required|string|min:5|max:2000',
        ]);

        $product = !empty($data['product_id']) ? Product::find($data['product_id']) : null;

        $enquiry = Enquiry::create([
            'customer_id' => $user?->id,
            'product_id'  => $product?->id,
            'name'        => $data['name'],
            'email'       => $data['email'],
            'phone'       => $data['phone'],
            'whatsapp'    => $data['whatsapp'],
            'message'     => $data['message'],
            'status'      => 'new',
        ]);
        $name = $data['name'];

        // Notify admins (user_id NULL = admin feed)
        Notification::create([
            'type'        => 'enquiry',
            'title'       => $product
                ? "New enquiry from {$enquiry->name} about \"{$product->name}\""
                : "New enquiry from {$enquiry->name}",
            'body'        => Str::limit($enquiry->message, 140),
            'link'        => '/enquiries',
            'entity_type' => Enquiry::class,
            'entity_id'   => $enquiry->id,
        ]);

        return response()->json([
            'message' => "Thanks {$name}! We'll get back to you soon.",
            'enquiry' => $enquiry->load('product:id,name,brand'),
        ], 201);
    }

    /**
     * GET /api/admin/enquiries
     * Filters:
     *   status — new | in_progress | responded | closed
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $q = Enquiry::query()
            ->with(['product:id,name,brand', 'customer:id,name,email'])
            ->latest();

        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        return response()->json($q->paginate(10));
    }

    /**
     * PUT /api/admin/enquiries/{id}
     * Admin updates status and/or sends a reply.
     * When reply or terminal status lands, notify the customer.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $enq = Enquiry::findOrFail($id);
        $previousStatus = $enq->status;
        $previousReply  = $enq->admin_reply;

        $data = $request->validate([
            'status'      => ['sometimes', Rule::in(Enquiry::STATUSES)],
            'admin_reply' => 'nullable|string|max:4000',
        ]);

        // If a reply text is added (or changed), mark replied_at + bump status
        // to "responded" unless the admin explicitly chose a status.
        $nowReply = array_key_exists('admin_reply', $data) ? $data['admin_reply'] : $enq->admin_reply;
        $replyChanged = trim((string) $nowReply) !== trim((string) $previousReply);

        $update = $data;
        if ($replyChanged && !empty($nowReply)) {
            $update['replied_at']  = now();
            $update['admin_reply'] = $nowReply;
            if (!isset($data['status'])) {
                $update['status'] = 'responded';
            }
        }
        // Keep resolved_at in sync for back-compat (closed = resolved).
        if (($update['status'] ?? $previousStatus) === 'closed' && !$enq->resolved_at) {
            $update['resolved_at'] = now();
        }
        if (isset($update['status']) && $update['status'] !== 'closed' && $previousStatus === 'closed') {
            $update['resolved_at'] = null;
        }

        $enq->update($update);

        // The admin acted on this enquiry → mark its admin-side notification read.
        Notification::markEntityRead(Enquiry::class, $enq->id, 'enquiry');

        // Notify the customer about the change (reply or status transition).
        $statusChanged = isset($data['status']) && $data['status'] !== $previousStatus;
        if (($replyChanged || $statusChanged) && $enq->customer_id) {
            $title = $replyChanged && !empty($nowReply)
                ? 'You have a new reply on your enquiry'
                : "Your enquiry is now " . str_replace('_', ' ', $update['status'] ?? $previousStatus);
            Notification::forCustomer($enq->customer_id, [
                'type'        => 'enquiry_reply',
                'title'       => $title,
                'body'        => $replyChanged && !empty($nowReply)
                    ? Str::limit($nowReply, 160)
                    : Str::limit($enq->message, 160),
                'link'        => '/dashboard/enquiries',
                'entity_type' => Enquiry::class,
                'entity_id'   => $enq->id,
            ]);
        }

        return response()->json($enq->fresh()->load(['product:id,name,brand', 'customer:id,name,email']));
    }

    /**
     * POST /api/admin/enquiries/{id}/resolve  (back-compat shortcut)
     * Equivalent to update(status=closed).
     */
    public function resolve(int $id): JsonResponse
    {
        $enq = Enquiry::findOrFail($id);
        $previousStatus = $enq->status;
        $enq->update([
            'status'      => 'closed',
            'resolved_at' => $enq->resolved_at ?? now(),
        ]);

        Notification::markEntityRead(Enquiry::class, $enq->id, 'enquiry');
        if ($previousStatus !== 'closed' && $enq->customer_id) {
            Notification::forCustomer($enq->customer_id, [
                'type'        => 'enquiry_reply',
                'title'       => 'Your enquiry has been closed',
                'body'        => Str::limit($enq->message, 160),
                'link'        => '/dashboard/enquiries',
                'entity_type' => Enquiry::class,
                'entity_id'   => $enq->id,
            ]);
        }

        return response()->json($enq);
    }

    /** DELETE /api/admin/enquiries/{id} */
    public function destroy(int $id): JsonResponse
    {
        $enq = Enquiry::findOrFail($id);
        Notification::markEntityRead(Enquiry::class, $enq->id);
        $enq->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
