<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeaveRequestStatus;
use App\Livewire\NotificationBell;
use App\Livewire\Notifications;
use App\Models\User;
use App\Notifications\LeaveRequestAwaitingHrApprovalNotification;
use App\Notifications\LeaveRequestStatusNotification;
use App\Notifications\NewLeaveRequestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_bell_shows_the_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new NewLeaveRequestNotification(1, 'Alice', 'Annual', '2026-01-01', '2026-01-02', 1.0, 'Trip'));
        $user->notify(new NewLeaveRequestNotification(2, 'Bob', 'Sick', '2026-01-03', '2026-01-03', 1.0, 'Flu'));

        $this->actingAs($user);

        Livewire::test(NotificationBell::class)->assertViewHas('unreadCount', 2);
    }

    public function test_marking_one_notification_as_read_decrements_the_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new NewLeaveRequestNotification(1, 'Alice', 'Annual', '2026-01-01', '2026-01-02', 1.0, 'Trip'));
        $notificationId = $user->notifications()->first()->id;

        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->call('markAsRead', $notificationId)
            ->assertViewHas('unreadCount', 0);

        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_marking_all_as_read_clears_the_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new NewLeaveRequestNotification(1, 'Alice', 'Annual', '2026-01-01', '2026-01-02', 1.0, 'Trip'));
        $user->notify(new NewLeaveRequestNotification(2, 'Bob', 'Sick', '2026-01-03', '2026-01-03', 1.0, 'Flu'));

        $this->actingAs($user);

        Livewire::test(NotificationBell::class)
            ->call('markAllAsRead')
            ->assertViewHas('unreadCount', 0);

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_a_user_only_ever_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->notify(new NewLeaveRequestNotification(1, 'Alice', 'Annual', '2026-01-01', '2026-01-02', 1.0, 'Trip'));

        $this->actingAs($user);

        Livewire::test(NotificationBell::class)->assertViewHas('unreadCount', 0);
    }

    public function test_the_index_page_lists_and_paginates_notifications(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 20; $i++) {
            $user->notify(new NewLeaveRequestNotification($i, "Employee {$i}", 'Annual', '2026-01-01', '2026-01-02', 1.0, 'Trip'));
        }

        $this->actingAs($user)->get(route('notifications.index'))->assertOk();

        Livewire::test(Notifications::class)->assertViewHas('notifications', fn ($paginator) => $paginator->total() === 20 && $paginator->count() === 15);
    }

    public function test_new_leave_request_notification_renders_its_expected_text_and_link(): void
    {
        $user = User::factory()->create();
        $user->notify(new NewLeaveRequestNotification(1, 'Alice', 'Annual Leave', '2026-01-01', '2026-01-02', 1.0, 'Trip'));

        $this->actingAs($user);

        Livewire::test(Notifications::class)
            ->assertSee('Alice requested Annual Leave leave')
            ->assertSee(route('leave.approvals'), false);
    }

    public function test_leave_request_awaiting_hr_approval_notification_renders_its_expected_text_and_link(): void
    {
        $user = User::factory()->create();
        $user->notify(new LeaveRequestAwaitingHrApprovalNotification(1, 'Alice', 'Annual Leave', '2026-01-01', '2026-01-02', 1.0, 'Morgan Manager'));

        $this->actingAs($user);

        Livewire::test(Notifications::class)
            ->assertSee("Alice's Annual Leave request needs your final approval")
            ->assertSee(route('admin.leave-approvals'), false);
    }

    public function test_leave_request_status_notification_renders_its_expected_text_and_link(): void
    {
        $user = User::factory()->create();
        $user->notify(new LeaveRequestStatusNotification(1, 'Annual Leave', '2026-01-01', '2026-01-02', LeaveRequestStatus::Approved, 'Harper HR', null));

        $this->actingAs($user);

        Livewire::test(Notifications::class)
            ->assertSee('Your Annual Leave request was approved')
            ->assertSee(route('leave.my-requests'), false);
    }
}
