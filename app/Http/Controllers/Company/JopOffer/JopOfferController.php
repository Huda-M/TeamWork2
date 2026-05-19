<?php

namespace App\Http\Controllers\Company\JopOffer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\JopOffer\SendJopOfferRequest;
use App\Mail\SendJopOfferMail;
use App\Models\JopOffer;
use App\Models\Programmer;
use Illuminate\Support\Facades\Mail;

class JopOfferController extends Controller
{
    public function store(SendJopOfferRequest $request)
    {
        $data = $request->validated();
        $data['company_name'] = auth()->user()->company->company_name ?? auth()->user()->full_name;
        $jopOffer = JopOffer::create($data);
        $programmer = Programmer::where('id', $request->programmer_id)->first();
        Mail::to($programmer->user->email)->send(new SendJopOfferMail($jopOffer, auth()->user()->email));

        return response()->json([
            'message' => 'Jop Offer created successfully',
        ], 201);
    }

    public function index()
    {
        $jopOffers = JopOffer::where('company_id', auth()->user()->id)->paginate(10);

        return response()->json([
            'message' => 'Jop Offers fetched successfully',
            'jopOffers' => $jopOffers->load('programmer.user'),
        ], 200);
    }
}
