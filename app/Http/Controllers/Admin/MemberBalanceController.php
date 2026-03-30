<?php

namespace App\Http\Controllers\Admin;

use App\Member;
use App\MemberBalanceLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberBalanceController extends Controller
{
    public function balanceList(Request $request)
    {
        $query = MemberBalanceLog::with('member')->orderBy('id', 'desc');

        if ($memberId = $request->input('member_id')) {
            $query->where('member_id', $memberId);
        }

        return view('admin.member_balance_list', [
            'list'           => $query->paginate(15)->appends($request->query()),
            'selectedMember' => $memberId ? Member::find($memberId) : null,
        ]);
    }

    public function balanceAddView()
    {
        return view('admin.member_balance_add');
    }

    public function balanceStore(Request $request)
    {
        $memberId = $request->input('member_id');
        $type     = (int) $request->input('type');
        $amount   = $request->input('amount');
        $remark   = $request->input('remark');

        if (empty($memberId)) {
            return $this->json(500, '請選擇會員');
        }
        if (!in_array($type, [1, 2])) {
            return $this->json(500, '類型錯誤');
        }
        if (!is_numeric($amount) || bccomp((string)$amount, '0', 2) <= 0) {
            return $this->json(500, '金額必須大於 0');
        }

        try {
            DB::transaction(function () use ($memberId, $type, $amount, $remark) {
                $member = Member::lockForUpdate()->findOrFail($memberId);

                $before = (string) $member->balance;

                if ($type === 2 && bccomp($before, (string)$amount, 2) < 0) {
                    throw new \Exception('餘額不足');
                }

                $after = $type === 1
                    ? bcadd($before, (string)$amount, 2)
                    : bcsub($before, (string)$amount, 2);

                $member->balance = $after;
                $member->save();

                MemberBalanceLog::create([
                    'member_id'      => $memberId,
                    'amount'         => $amount,
                    'before_balance' => $before,
                    'after_balance'  => $after,
                    'type'           => $type,
                    'remark'         => $remark,
                ]);
            });

            return $this->json(200, '調整成功');
        } catch (\Exception $e) {
            return $this->json(500, $e->getMessage());
        }
    }
}
