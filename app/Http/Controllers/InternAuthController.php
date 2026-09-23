<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Intern;
use App\Models\TimeLog;
use App\Models\Message;
use App\Models\GradeSubmission;
use App\Models\DocumentRequest;
use App\Models\OTP;
use App\Services\EmailService;
use Carbon\Carbon;

class InternAuthController extends Controller
{
    public function showLoginForm()
    {
        return view('intern-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower(trim($request->email));
        $intern = Intern::where('email', $email)->first();

        if (!$intern || !\Hash::check($request->password, $intern->password)) {
            return back()->with('error', 'Invalid intern credentials.');
        }

        // Must be accepted by admin before login
        if ($intern->status !== 'accepted') {
            return back()->with('error', "Please wait for the Admin's Approval.");
        }

        Auth::guard('intern')->login($intern);
        
        // If no supervisor assigned, redirect to select one first
        if (!$intern->supervisor_id) {
            return redirect()->route('intern.select-supervisor')
                ->with('info', 'Please select a supervisor to manage your attendance.');
        }
        
        // If phases complete -> dashboard; else -> phase submission page
        if ($intern->hasCompletedAllPhases()) {
            return redirect()->route('intern.dashboard');
        }

        return redirect()->route('intern.dashboard');
    }

    public function logout()
    {
        Auth::guard('intern')->logout();
        return redirect()->route('intern.login');
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $intern = Auth::guard('intern')->user();
        $file = $request->file('avatar');

        if ($intern->avatar && Storage::disk('public')->exists($intern->avatar)) {
            Storage::disk('public')->delete($intern->avatar);
        }

        $path = $file->store('interns/avatars', 'public');
        $intern->avatar = $path;
        $intern->save();

        return back()->with('success', 'Profile image updated successfully.');
    }

    public function dashboard()
    {
        $intern = Auth::guard('intern')->user();

        // Calculate total OJT hours from TimeLog
        $logs = TimeLog::where('intern_id', $intern->id)->get();
        $totalSeconds = 0;

        foreach ($logs as $log) {
            $in = $log->time_in ? Carbon::parse($log->date . ' ' . $log->time_in, 'Asia/Manila') : null;
            $out = $log->time_out ? Carbon::parse($log->date . ' ' . $log->time_out, 'Asia/Manila') : null;

            if ($in && !$out) {
                $out = Carbon::parse($log->date . ' 17:00:00', 'Asia/Manila');
            }

            if ($in && $out) {
                $totalSeconds += $in->diffInSeconds($out);
            }
        }

        $totalHours = round($totalSeconds / 3600, 2);
        $remainingHours = max(0, 486 - $totalHours);
        $progressPercent = min(100, ($totalHours / 486) * 100);

        // Count unread messages from admin
        $unreadMessages = Message::where('receiver_id', $intern->id)
            ->where('receiver_type', 'intern')
            ->where('sender_type', 'admin')
            ->where('is_read', false)
            ->count();

        // Get pending document requests
        $pendingRequests = DocumentRequest::where('intern_id', $intern->id)
            ->pluck('type')
            ->toArray();

        // Check if it's Friday and intern hasn't submitted journal this week
        $isFriday = now('Asia/Manila')->isFriday();
        $hasSubmittedJournalThisWeek = $intern->hasSubmittedJournalThisWeek();

        // Check if end of month is approaching
        $daysUntilEndOfMonth = now('Asia/Manila')->endOfMonth()->diffInDays(now('Asia/Manila'));

        return view('intern-dashboard', compact(
            'intern',
            'totalHours',
            'remainingHours',
            'progressPercent',
            'unreadMessages',
            'pendingRequests',
            'isFriday',
            'hasSubmittedJournalThisWeek',
            'daysUntilEndOfMonth'
        ));
    }

    public function endorsement()
    {
        $intern = Auth::guard('intern')->user();

        $data = [
            'supervisor_name' => $intern->supervisor_name,
            'supervisor_position' => $intern->supervisor_position,
            'company_name' => $intern->company_name,
            'company_address' => $intern->company_address,
            'interns' => [ $intern->first_name . ' ' . $intern->last_name ],
            'sentAt' => now('Asia/Manila'),
        ];

        return view('Endorsement', $data);
    }

    public function acceptanceLetter()
    {
        $intern = Auth::guard('intern')->user();

        return view('Acceptance-Letter', [
            'intern' => $intern,
            'today' => now('Asia/Manila'),
        ]);
    }

    public function memorandum()
    {
        $intern = Auth::guard('intern')->user();

        return view('memorandum', [
            'intern' => $intern,
            'today' => now('Asia/Manila'),
        ]);
    }

    public function internshipContract()
    {
        $intern = Auth::guard('intern')->user();

        return view('internship-contract', [
            'intern' => $intern,
            'today' => now('Asia/Manila'),
        ]);
    }

    public function showSendDataForm()
    {
        $intern = Auth::guard('intern')->user();

        $requests = DocumentRequest::where('intern_id', $intern->id)
            ->pluck('type')
            ->toArray();

        return view('send-data', compact('intern', 'requests'));
    }

    public function phaseSubmission()
    {
        $intern = Auth::guard('intern')->user();
        return view('phase-submission', compact('intern'));
    }

    public function uploadDocx(Request $request)
    {
        $request->validate([
            'semester'   => 'required|in:1st,2nd,3rd,4th',
            'grade_doc'  => 'required|file|mimes:doc,docx|max:10240',
        ]);

        $intern = Auth::guard('intern')->user();

        // Store file in storage/app/public/grades
        $file = $request->file('grade_doc');
        $filename = now()->format('YmdHis') . "_intern{$intern->id}." . $file->getClientOriginalExtension();
        $path = $file->storeAs('grades', $filename, 'public');

        // Save or update grade submission
        GradeSubmission::updateOrCreate(
            ['intern_id' => $intern->id, 'semester' => $request->semester],
            ['file_path' => $path, 'submitted_at' => now()]
        );

        // Map semester to request type
        $typeMap = [
            '1st' => 'midterm',
            '2nd' => 'final',
            '3rd' => 'certificate',
            '4th' => 'evaluation',
        ];

        $matchedType = $typeMap[$request->semester] ?? null;

        if ($matchedType) {
            DocumentRequest::where('intern_id', $intern->id)
                ->where('type', $matchedType)
                ->delete();
        }

        return redirect()->route('intern.dashboard')->with('success', 'File successfully uploaded.');
    }

    /**
     * Show the Daily Time Record page for the logged-in intern.
     */
    public function dtr()
    {
        $intern = Auth::guard('intern')->user();

        // Fetch all time logs for this intern
        $logs = TimeLog::where('intern_id', $intern->id)
            ->orderBy('date', 'desc')
            ->get();

        // Calculate total OJT hours from TimeLog
        $totalSeconds = 0;

        foreach ($logs as $log) {
            $in = $log->time_in ? Carbon::parse($log->date . ' ' . $log->time_in, 'Asia/Manila') : null;
            $out = $log->time_out ? Carbon::parse($log->date . ' ' . $log->time_out, 'Asia/Manila') : null;

            if ($in && !$out) {
                $out = Carbon::parse($log->date . ' 17:00:00', 'Asia/Manila');
            }

            if ($in && $out) {
                $totalSeconds += $in->diffInSeconds($out);
            }
        }

        $totalHours = round($totalSeconds / 3600, 2);

        return view('intern-dtr', compact('intern', 'logs', 'totalHours'));
    }

    // Show supervisor selection form
    public function showSupervisorSelection()
    {
        $intern = Auth::guard('intern')->user();
        $supervisors = \App\Models\Supervisor::where('is_accepted', true)->get();
        
        return view('intern-select-supervisor', compact('intern', 'supervisors'));
    }

    // Update supervisor for intern
    public function updateSupervisor(Request $request)
    {
        $intern = Auth::guard('intern')->user();
        
        $request->validate([
            'supervisor_id' => 'required|exists:supervisors,id',
        ]);

        $supervisor = \App\Models\Supervisor::findOrFail($request->supervisor_id);

        $intern->update([
            'supervisor_id' => $supervisor->id,
        ]);

        return redirect()->route('intern.dashboard')
            ->with('success', 'Connected to ' . $supervisor->name . ' successfully!');
    }

    /**
     * Send OTP for email verification
     */
    public function sendOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));

        try {
            // Create OTP record
            $otp = OTP::createOTP($email, 'verification', 10);

            // Send email
            $emailService = new EmailService();
            $emailService->sendOTPEmail($email, $otp->otp_code, 'Intern');

            return response()->json([
                'success' => true,
                'message' => 'OTP sent to your email address.',
            ]);
        } catch (\Exception $e) {
            \Log::error('OTP sending error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify OTP for email verification
     */
    public function verifyOTPSubmit(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $email = strtolower(trim($request->email));
        $code = trim($request->otp);

        // Verify OTP
        $otp = OTP::verifyOTP($email, $code, 'verification');

        if (!$otp) {
            return back()->with('error', 'Invalid or expired OTP code.');
        }

        // Mark OTP as used
        $otp->markAsUsed();

        // Find and update intern
        $intern = Intern::where('email', $email)->first();
        if ($intern) {
            $intern->update(['email_verified_at' => now()]);
        }

        return redirect()->route('intern.login')->with('success', 'Email verified successfully! You can now login.');
    }

    /**
     * Resend OTP for email verification
     */
    public function resendOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));

        try {
            // Create new OTP record
            $otp = OTP::createOTP($email, 'verification', 10);

            // Send email
            $emailService = new EmailService();
            $emailService->sendOTPEmail($email, $otp->otp_code, 'Intern');

            return response()->json([
                'success' => true,
                'message' => 'New OTP sent to your email address.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Resend OTP error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Send OTP for password reset
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));

        // Check if email exists
        $intern = Intern::where('email', $email)->first();
        if (!$intern) {
            return response()->json([
                'success' => false,
                'message' => 'Email address not found in our system.',
            ], 404);
        }

        try {
            // Create OTP for password reset
            $otp = OTP::createOTP($email, 'password_reset', 10);

            // Send email
            $emailService = new EmailService();
            $emailService->sendPasswordResetOTPEmail($email, $otp->otp_code, $intern->first_name);

            return response()->json([
                'success' => true,
                'message' => 'Password reset OTP sent to your email.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Forgot password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process password reset. Please try again.',
            ], 500);
        }
    }

    /**
     * Verify OTP for password reset
     */
    public function verifyPasswordResetOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $email = strtolower(trim($request->email));
        $code = trim($request->otp);

        // Verify OTP
        $otp = OTP::verifyOTP($email, $code, 'password_reset');

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
        ]);
    }

    /**
     * Resend OTP for password reset
     */
    public function resendPasswordResetOTP(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($request->email));

        try {
            // Create new OTP
            $otp = OTP::createOTP($email, 'password_reset', 10);

            // Send email
            $emailService = new EmailService();
            $intern = Intern::where('email', $email)->first();
            $emailService->sendPasswordResetOTPEmail($email, $otp->otp_code, $intern ? $intern->first_name : 'User');

            return response()->json([
                'success' => true,
                'message' => 'New OTP sent to your email.',
            ]);
        } catch (\Exception $e) {
            \Log::error('Resend password reset OTP error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend OTP. Please try again.',
            ], 500);
        }
    }

    /**
     * Reset password after OTP verification
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($request->email));
        $code = trim($request->otp);

        // Verify OTP one more time
        $otp = OTP::verifyOTP($email, $code, 'password_reset');

        if (!$otp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 400);
        }

        // Find intern and update password
        $intern = Intern::where('email', $email)->first();
        if (!$intern) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // Update password
        $intern->update([
            'password' => \Hash::make($request->password),
        ]);

        // Mark OTP as used
        $otp->markAsUsed();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login with your new password.',
        ]);
    }
}

