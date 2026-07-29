<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with('replies.user')->where('user_id', $request->user()->id);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        return $query->latest()->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'required|in:low,medium,high',
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'priority' => $data['priority'],
            'status' => 'pending',
        ]);

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
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        $ticket->update(['status' => 'pending']);

        return response()->json($reply->load('user'), 201);
    }
}
