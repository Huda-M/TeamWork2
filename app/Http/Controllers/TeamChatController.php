<?php

namespace App\Http\Controllers;

use App\Events\TeamMessageSent;
use App\Models\Message;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamChatController extends Controller
{
    public function messages(Team $team)
    {
        $this->authorizeTeamMember($team);

        $room = $team->chatRoom;

        return $room->messages()
            ->with('user:id,full_name')
            ->latest()
            ->paginate(30);
    }

    public function send(Request $request, Team $team)
    {
        $this->authorizeTeamMember($team);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $room = $team->chatRoom;

        if (! $room) {
            $room = $team->chatRoom()->create();
        }

        $message = Message::create([
            'chat_room_id' => $room->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        broadcast(new TeamMessageSent($message))->toOthers();

        return response()->json([
            'message' => 'Message sent successfully',
            'data' => $message->load('user:id,full_name'),
        ], 201);
    }

    private function authorizeTeamMember(Team $team): void
    {
        $programmer = auth()->user()->programmer;
        abort_unless(
            $team->teamMembers()->where('programmer_id', $programmer->id)->exists(),
            403,
            'You are not a member of this team.'
        );
    }
}
