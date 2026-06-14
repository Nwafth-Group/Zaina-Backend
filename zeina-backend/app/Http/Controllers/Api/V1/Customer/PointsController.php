<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\RewardTasks;
use App\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PointsController extends Controller
{
    use ApiResponseTrait;

public function summary()
{  
    $user = auth()->user();
    
    // 1. حساب إجمالي النقاط الحالي للعميل
    $totalPoints = $user->pointTransactions()->where('type', 'earn')->sum('amount') 
                 - $user->pointTransactions()->where('type', 'redeem')->sum('amount');

    $pointSetting = DB::table('point_settings')->where('key', 'service_cycle_rules')->first();    
    $targetServices = $pointSetting ? (int) $pointSetting->target_steps : 3;
    $rewardPoints   = $pointSetting ? (int) $pointSetting->reward_points : 100;
    $actionType     = $pointSetting ? $pointSetting->action_type : 'complete_bookings';

    // حساب عدد الحجوزات المكتملة بناءً على نوع التصرف
    $completedCount = $user->bookings()->where('status', 'completed')->count();
    
    // معادلة حساب الدورة الحالية (باقي القسمة يضمن تصفير العداد والبدء من جديد فور التعبئة)
    $currentCycleProgress = $completedCount % $targetServices;
    $stepsRemaining = $targetServices - $currentCycleProgress;

    // 3. جلب مهام كسب النقاط الديناميكية (Reward Tasks)
    $completedTaskIds = DB::table('user_reward_tasks')
                          ->where('user_id', $user->id)
                          ->pluck('task_id')
                          ->toArray();

    $tasks = RewardTasks::where('status', true)->get()->take(3)->map(function ($task) use ($completedTaskIds) {
        return [
            'id'           => $task->id,
            'title'        => app()->getLocale() == 'ar' ? $task->title_ar : $task->title_en,
            'subtitle'     => app()->getLocale() == 'ar' ? $task->subtitle_ar : $task->subtitle_en,
            'points'       => '+' . $task->points . ' Pts',
            'action_type'  => $task->action_type,
            'action_link'  => $task->action_link,
            'is_completed' => in_array($task->id, $completedTaskIds),
        ];
    });

    // 4. جلب آخر 3 حركات في سجل النقاط
    $recentHistory = $user->pointTransactions()
                          ->latest()
                          ->take(3)
                          ->get()
                          ->map(function ($tx) {
                              return [
                                  'id'     => $tx->id,
                                  'type'   => $tx->type,
                                  'title'  => app()->getLocale() == 'ar' ? $tx->title_ar : $tx->title_en,
                                  'amount' => ($tx->type == 'earn' ? '+' : '-') . $tx->amount . ' Pts',
                                  'date'   => $tx->created_at->format('d M. Y'),
                              ];
                          });
 
    return $this->successResponse([
         'total_points' => $totalPoints,
         'progress_card' => [
             // الترجمة بتصير تستقبل الـ target_steps المحقونة ديناميكياً من الأدمن
             'title'         => __('messages.points.complete_services_title', ['count' => $targetServices]),
             'current_step'  => $currentCycleProgress,
             'target_step'   => $targetServices,
             'reward_points' => $rewardPoints,
             'footer_text'   => __('messages.points.steps_remaining', ['count' => $stepsRemaining]),
             'action_type'   => $actionType, // نرسلها للفرونت إند عشان الموبايل يوجه العميل للشاشة الصح
         ],
         'earn_points' => $tasks,
         'history'     => $recentHistory
     ], __('messages.points.summary_retrieved'));
}

public function history(Request $request)
{ 
    $user = auth()->user();
    $query = $user->pointTransactions()->latest();
    if ($request->has('filter')) {
        $filter = $request->filter;

        switch ($filter) {
            case 'today':
                $query->whereDate('created_at', Carbon::today());
                break;
            case 'last_7_days':
                $query->where('created_at', '>=', Carbon::now()->subDays(7));
                break;
            case 'last_30_days':
                $query->where('created_at', '>=', Carbon::now()->subDays(30));
                break;
           case 'custom':
                $query->whereBetween('created_at', [
                    $request->start_date . ' 00:00:00',
                    $request->end_date . ' 23:59:59'
                ]);
                break;
        }
    }

    // نعمل Pagination عشان لو الـ History طويل ما يعلق الموبايل
    $history = $query->paginate(15);

    // تجهيز الداتا للـ Resource
    $formattedHistory = $history->getCollection()->map(function($tx) {
        return [
            'id' => $tx->id,
            'type' => $tx->type, // 'earn' عشان يعطيه سهم أخضر لفوق، 'redeem' سهم أحمر لتحت
            'title' => $tx->title_en, 
            'amount' => $tx->formatted_amount,
            'date' => $tx->created_at->format('d M. Y') 
        ];
    });

    return $this->successResponse($formattedHistory, __('messages.points.history_retrieved'));
}
public function completeTask(Request $request)
{
    $request->validate([
        'task_id' => 'required|exists:reward_tasks,id',
    ]);

    $user = auth()->user();
    $task = RewardTasks::findOrFail($request->task_id);

    // حماية تجريبية: نمنع التكرار إذا كانت المهمة من النوع الذي ينجر لمرة واحدة فقط
    $alreadyCompleted = DB::table('user_reward_tasks')
        ->where('user_id', $user->id)
        ->where('task_id', $task->id)
        ->exists();

    if ($alreadyCompleted) {
        return $this->errorResponse(__('messages.points.task_already_completed'), 422);
    }

    DB::beginTransaction();
    try {
        // 1. تسجيل المهمة كمكتملة للمستخدم
        DB::table('user_reward_tasks')->insert([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. إضافة النقاط بجدول الحركات (Transactions)
        $user->pointTransactions()->create([
            'type' => 'earn',
            'amount' => $task->points,
            'title_ar' => 'مكافأة إتمام مهمة: ' . $task->title_ar,
            'title_en' => 'Task Reward: ' . $task->title_en,
        ]);

        DB::commit();
        return $this->successResponse(null, __('messages.points.task_completed_and_points_earned', ['points' => $task->points]));

    } catch (\Exception $e) {
        DB::rollBack();
        return $this->errorResponse('حدث خطأ: ' . $e->getMessage(), 500);
    }
}


public function earnPoints()
{
    $user = auth()->user();
    $completedTaskIds = DB::table('user_reward_tasks')
                          ->where('user_id', $user->id)
                          ->pluck('task_id')
                          ->toArray();
    $tasks = RewardTasks::where('status', true)->get()->map(function ($task) use ($completedTaskIds) {
        return [
            'id'           => $task->id,
            'title'        => app()->getLocale() == 'ar' ? $task->title_ar : $task->title_en,
            'subtitle'     => app()->getLocale() == 'ar' ? $task->subtitle_ar : $task->subtitle_en,
            'points'       => '+' . $task->points . ' Pts',
            'action_type'  => $task->action_type,
            'action_link'  => $task->action_link,
            'is_completed' => in_array($task->id, $completedTaskIds),
        ];
    });

    return $this->successResponse($tasks, __('messages.points.earn_points_retrieved'));
}





}
