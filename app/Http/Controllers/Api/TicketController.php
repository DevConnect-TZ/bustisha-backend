<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Services\UploadSanitizer;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        return Ticket::with('replies.user')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
            'transaction_id' => 'nullable|integer|exists:transactions,id',
        ]);

        $ticketData = [
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'priority' => $data['priority'],
            'status' => 'pending',
        ];

        if (isset($data['transaction_id'])) {
            $transaction = Transaction::findOrFail($data['transaction_id']);
            abort_unless($transaction->user_id === $request->user()->id, 403);
            $ticketData['transaction_id'] = $transaction->id;
        }

        $ticket = Ticket::create($ticketData);

        if ($request->hasFile('attachment')) {
            $result = app(UploadSanitizer::class)->sanitize($request->file('attachment'));
            if (!$result['safe']) {
                $ticket->delete();
                return response()->json(['message' => $result['message']], 422);
            }
            $path = $result['file']->store('tickets', 'public');
            $ticket->replies()->create([
                'user_id' => $request->user()->id,
                'message' => 'Payment screenshot attached.',
                'attachment' => $path,
            ]);
        }

        return response()->json($ticket->load('replies.user'), 201);
    }

    public function show(Ticket $ticket)
    {
        if ($ticket->user_id !== request()->user()->id && request()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        return $ticket->load('user', 'replies.user');
    }

    public function reply(Request $request, Ticket $ticket)
    {
        if ($ticket->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($ticket->status === 'closed') {
            return response()->json(['message' => 'Ticket is closed.'], 422);
        }

        $data = $request->validate([
            'message' => 'required|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $replyData = [
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ];

        if ($request->hasFile('attachment')) {
            $result = app(UploadSanitizer::class)->sanitize($request->file('attachment'));
            if (!$result['safe']) {
                return response()->json(['message' => $result['message']], 422);
            }
            $replyData['attachment'] = $result['file']->store('tickets', 'public');
        }

        $reply = TicketReply::create($replyData);
        $ticket->update(['status' => 'pending']);

        return response()->json($reply->load('user'), 201);
    }
}
