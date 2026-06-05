<?php

namespace App\Http\Controllers;

use App\Events\TeamMessageSent;
use App\Models\Message;
use App\Models\Team;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class TeamChatController extends Controller
{
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

    public function TeamMessages(Team $team)
    {
        $this->authorizeTeamMember($team);
        $room = $team->chatRoom()->firstOrCreate([]);

        return response()->json([
            'message' => 'Messages retrieved successfully',
            'data' => $room->messages()
            ->with('user:id,full_name')
            ->latest()
            ->get(),
        ]);
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

    public function MyChats()
    {
        $user = auth()->user();
        if (!$user || !$user->programmer) {
            return response()->json([
                'success' => false,
                'message' => 'Programmer profile not found',
            ], 404);
        }

        $programmer = $user->programmer;

        // Retrieve teams where the programmer is currently an active member, filtered using Spatie QueryBuilder
        $teamsQuery = $programmer->teams()
            ->wherePivotNull('left_at')
            ->with(['chatRoom.latestMessage.user:id,full_name']);

        $teams = QueryBuilder::for($teamsQuery)
            ->allowedFilters([
                AllowedFilter::partial('name'),
            ])
            ->get();

        $chats = $teams->map(function ($team) {
            $chatRoom = $team->chatRoom ?: $team->chatRoom()->create();
            $latestMessage = $chatRoom->latestMessage;

            return [
                'chat_room_id' => $chatRoom->id,
                'team_id' => $team->id,
                'team_name' => $team->name,
                'avatar_url' => $team->avatar_url,
                'latest_message' => $latestMessage ? [
                    'id' => $latestMessage->id,
                    'body' => $latestMessage->body,
                    'user' => [
                        'id' => $latestMessage->user->id ?? null,
                        'full_name' => $latestMessage->user->full_name ?? null,
                    ],
                    'created_at' => $latestMessage->created_at,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Chats retrieved successfully',
            'data' => $chats,
        ]);
    }
}
