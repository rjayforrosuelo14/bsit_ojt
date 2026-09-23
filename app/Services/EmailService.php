<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\Log;

class EmailService
{
    private $mail;
    private $config;

    public function __construct()
    {
        $this->mail = new PHPMailer(true);
        $this->config = [
            'host' => env('MAIL_HOST', 'smtp.gmail.com'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME', 'ojtmonitoring71@gmail.com'),
            'password' => env('MAIL_PASSWORD', 'zfth lhql kukx ejmp'),
            'from_email' => env('MAIL_FROM_ADDRESS', 'ojtmonitoring71@gmail.com'),
            'from_name' => env('MAIL_FROM_NAME', 'OJT Monitoring System'),
            'charset' => 'UTF-8',
        ];

        $this->configureMailer();
    }

    /**
     * Configure PHPMailer with SMTP settings
     */
    private function configureMailer()
    {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = $this->config['host'];
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->config['username'];
            $this->mail->Password = $this->config['password'];
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = $this->config['port'];
            $this->mail->CharSet = $this->config['charset'];

            // Default sender
            $this->mail->setFrom($this->config['from_email'], $this->config['from_name']);
        } catch (Exception $e) {
            Log::error('PHPMailer Configuration Error: ' . $e->getMessage());
        }
    }

    /**
     * Send OTP verification email
     *
     * @param string $recipientEmail
     * @param string $otp
     * @param string $userName
     * @return bool
     */
    public function sendOTPEmail($recipientEmail, $otp, $userName = 'User')
    {
        try {
            // Clear previous recipients
            $this->mail->clearAllRecipients();

            // Recipients
            $this->mail->addAddress($recipientEmail, $userName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Email Verification - OJT Monitoring System';
            $this->mail->Body = $this->getOTPEmailTemplate($otp, $userName);
            $this->mail->AltBody = "Your OTP code is: $otp. This code expires in 10 minutes.";

            // Send
            $result = $this->mail->send();
            
            Log::info("OTP Email sent successfully to: $recipientEmail");
            return true;
        } catch (Exception $e) {
            Log::error("OTP Email could not be sent to $recipientEmail. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send password reset OTP email
     *
     * @param string $recipientEmail
     * @param string $otp
     * @param string $userName
     * @return bool
     */
    public function sendPasswordResetOTPEmail($recipientEmail, $otp, $userName = 'User')
    {
        try {
            // Clear previous recipients
            $this->mail->clearAllRecipients();

            // Recipients
            $this->mail->addAddress($recipientEmail, $userName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Password Reset - OJT Monitoring System';
            $this->mail->Body = $this->getPasswordResetOTPEmailTemplate($otp, $userName);
            $this->mail->AltBody = "Your password reset OTP code is: $otp. This code expires in 10 minutes.";

            // Send
            $result = $this->mail->send();
            
            Log::info("Password Reset OTP Email sent successfully to: $recipientEmail");
            return true;
        } catch (Exception $e) {
            Log::error("Password Reset OTP Email could not be sent to $recipientEmail. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send welcome email
     *
     * @param string $recipientEmail
     * @param string $userName
     * @param string $loginUrl
     * @return bool
     */
    public function sendWelcomeEmail($recipientEmail, $userName, $loginUrl = null)
    {
        try {
            // Clear previous recipients
            $this->mail->clearAllRecipients();

            // Recipients
            $this->mail->addAddress($recipientEmail, $userName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = 'Welcome to OJT Monitoring System';
            $this->mail->Body = $this->getWelcomeEmailTemplate($userName, $loginUrl);
            $this->mail->AltBody = "Welcome to the OJT Monitoring System, $userName!";

            // Send
            $result = $this->mail->send();
            
            Log::info("Welcome Email sent successfully to: $recipientEmail");
            return true;
        } catch (Exception $e) {
            Log::error("Welcome Email could not be sent to $recipientEmail. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send document submission notification email
     *
     * @param string $recipientEmail
     * @param string $userName
     * @param string $documentType
     * @return bool
     */
    public function sendDocumentSubmissionEmail($recipientEmail, $userName, $documentType)
    {
        try {
            // Clear previous recipients
            $this->mail->clearAllRecipients();

            // Recipients
            $this->mail->addAddress($recipientEmail, $userName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = "Document Submission Confirmation - $documentType";
            $this->mail->Body = $this->getDocumentSubmissionTemplate($userName, $documentType);
            $this->mail->AltBody = "Your $documentType has been successfully submitted.";

            // Send
            $result = $this->mail->send();
            
            Log::info("Document Submission Email sent successfully to: $recipientEmail");
            return true;
        } catch (Exception $e) {
            Log::error("Document Submission Email could not be sent to $recipientEmail. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send generic email
     *
     * @param string $recipientEmail
     * @param string $subject
     * @param string $htmlBody
     * @param string|null $recipientName
     * @return bool
     */
    public function sendEmail($recipientEmail, $subject, $htmlBody, $recipientName = null)
    {
        try {
            // Clear previous recipients
            $this->mail->clearAllRecipients();

            // Recipients
            $this->mail->addAddress($recipientEmail, $recipientName);

            // Content
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body = $htmlBody;
            $this->mail->AltBody = strip_tags($htmlBody);

            // Send
            $result = $this->mail->send();
            
            Log::info("Email sent successfully to: $recipientEmail with subject: $subject");
            return true;
        } catch (Exception $e) {
            Log::error("Email could not be sent to $recipientEmail. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Get OTP email HTML template
     */
    private function getOTPEmailTemplate($otp, $userName)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #457b9d, #1d3557); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; text-align: center; }
                .otp-box { background-color: #f8f9fa; border-left: 4px solid #457b9d; padding: 20px; margin: 20px 0; border-radius: 4px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #457b9d; letter-spacing: 3px; font-family: 'Courier New', monospace; }
                .info { font-size: 14px; color: #666; margin-top: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
                .warning { color: #ff6b6b; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Email Verification</h1>
                    <p>OJT Monitoring System</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>$userName</strong>,</p>
                    <p>Thank you for registering with our OJT Monitoring System. To verify your email address, please use the following One-Time Password (OTP):</p>
                    <div class='otp-box'>
                        <div class='otp-code'>$otp</div>
                    </div>
                    <div class='info'>
                        <p class='warning'>⏱️ This code expires in 10 minutes</p>
                        <p>Never share this code with anyone. If you didn't request this verification, please ignore this email.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " OJT Monitoring System. All rights reserved.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get password reset OTP email HTML template
     */
    private function getPasswordResetOTPEmailTemplate($otp, $userName)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; text-align: center; }
                .otp-box { background-color: #f8f9fa; border-left: 4px solid #e74c3c; padding: 20px; margin: 20px 0; border-radius: 4px; }
                .otp-code { font-size: 32px; font-weight: bold; color: #e74c3c; letter-spacing: 3px; font-family: 'Courier New', monospace; }
                .info { font-size: 14px; color: #666; margin-top: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
                .warning { color: #ff6b6b; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                    <p>OJT Monitoring System</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>$userName</strong>,</p>
                    <p>We received a request to reset your password. Please use the following One-Time Password (OTP) to proceed:</p>
                    <div class='otp-box'>
                        <div class='otp-code'>$otp</div>
                    </div>
                    <div class='info'>
                        <p class='warning'>⏱️ This code expires in 10 minutes</p>
                        <p>If you didn't request a password reset, please ignore this email and your password will remain unchanged.</p>
                        <p>For security reasons, never share this code with anyone.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " OJT Monitoring System. All rights reserved.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get welcome email HTML template
     */
    private function getWelcomeEmailTemplate($userName, $loginUrl = null)
    {
        $loginSection = $loginUrl ? 
            "<p><a href='$loginUrl' style='background-color: #457b9d; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block;'>Login Now</a></p>" 
            : "";

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #10b981, #059669); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; }
                .feature-list { text-align: left; margin: 20px 0; }
                .feature-item { padding: 10px; border-left: 3px solid #10b981; margin-bottom: 10px; background-color: #f8f9fa; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome, $userName!</h1>
                    <p>OJT Monitoring System</p>
                </div>
                <div class='content'>
                    <p>We're thrilled to have you join our OJT Monitoring System!</p>
                    <p>Your account has been successfully created and verified. You now have full access to:</p>
                    <div class='feature-list'>
                        <div class='feature-item'>✓ Monitor your OJT progress and hours</div>
                        <div class='feature-item'>✓ Submit and track documents</div>
                        <div class='feature-item'>✓ Communicate with supervisors</div>
                        <div class='feature-item'>✓ View daily attendance records</div>
                        <div class='feature-item'>✓ Access important announcements</div>
                    </div>
                    <p style='text-align: center; margin-top: 30px;'>$loginSection</p>
                    <p style='font-size: 12px; color: #999; text-align: center;'>If the button above doesn't work, copy and paste this link: <br><code>$loginUrl</code></p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " OJT Monitoring System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get document submission confirmation template
     */
    private function getDocumentSubmissionTemplate($userName, $documentType)
    {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
                .header { background: linear-gradient(135deg, #3b82f6, #1e40af); color: white; padding: 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 30px; text-align: center; }
                .success-badge { display: inline-block; background-color: #d1fae5; color: #065f46; padding: 12px 30px; border-radius: 20px; font-weight: bold; margin: 20px 0; }
                .info { font-size: 14px; color: #666; margin-top: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✓ Document Submitted</h1>
                    <p>OJT Monitoring System</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>$userName</strong>,</p>
                    <p>Your document has been successfully submitted!</p>
                    <div class='success-badge'>$documentType</div>
                    <div class='info'>
                        <p>We've received your submission and it is now under review. You will be notified once it has been processed.</p>
                        <p>If you need to resubmit or have any questions, please log in to your account or contact our support team.</p>
                    </div>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " OJT Monitoring System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Get PHPMailer instance for advanced usage
     */
    public function getMailerInstance()
    {
        return $this->mail;
    }
}
