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
    $totalPoints = $user->pointTransactions()->where('type', 'earn')->sum('amount') 
                 - $user->pointTransactions()->where('type', 'redeem')->sum('amount');

    // 2. منطق شريط التقدم (Complete 3 Services)
    $targetServices = 3;
    $completedCount = $user->bookings()->where('status', 'completed')->count();
    $currentCycleProgress = $completedCount % $targetServices;

    // 3. جلب مهام كسب النقاط الديناميكية (Reward Tasks)
    // نتحقق من المهام التي لم ينجزها المستخدم بعد أو نحدد حالتها
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
            // 'icon'         => $task->media->where('collection', 'reward_tasks')->first()?->getUrl(),
            'is_completed' => in_array($task->id, $completedTaskIds),
        ];
    });

    // 4. جلب آخر 3 حركات في السجل (Recent History)
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
             'title'         => __('messages.points.complete_services_title', ['count' => $targetServices]),
             'current_step'  => $currentCycleProgress,
             'target_step'   => $targetServices,
             'reward_points' => 100,
             'footer_text'   => __('messages.points.steps_remaining', ['count' => $targetServices - $currentCycleProgress]),
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





}
