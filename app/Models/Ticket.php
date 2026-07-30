<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'transaction_id', 'subject', 'message', 'priority', 'status'];

    public function user() { return $this->belongsTo(User::class); }
    public function replies() { return $this->hasMany(TicketReply::class); }
    public function transaction() { return $this->belongsTo(Transaction::class); }
}
