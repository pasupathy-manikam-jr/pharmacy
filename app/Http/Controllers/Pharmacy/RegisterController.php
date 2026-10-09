<?php

namespace App\Http\Controllers\Pharmacy;

use App\Http\Controllers\Controller;
use App\Models\PoisonRegisterEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public const REGISTERS = ['prescription_book', 'poisons_book', 'psychotropic', 'dda'];

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'register' => ['nullable', 'in:'.implode(',', self::REGISTERS)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $register = $filters['register'] ?? 'prescription_book';
        $from = $filters['from'] ?? today()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? today()->toDateString();

        return Inertia::render('register/Index', [
            'entries' => PoisonRegisterEntry::query()
                ->where('branch_id', $request->user()?->branch_id)
                ->where('register', $register)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->with(['product:id,name,strength,unit', 'batch:id,batch_no', 'pharmacist:id,name'])
                ->orderBy('id')
                ->get(),
            'filters' => ['register' => $register, 'from' => $from, 'to' => $to],
            'registers' => self::REGISTERS,
        ]);
    }
}
