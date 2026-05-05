<?php
/**
 * Authentication API
 * Handles user registration, login, and email verification
 */

if (!session_id()) {
    session_start();
}

class AuthHandler {
    private $db;
    private $emailDomain = "@localhost"; // Change this to your actual domain
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Register a new teacher
     * Returns: ['success' => bool, 'message' => string, 'user_id' => int|null]
     */
    public function registerTeacher($fullname, $email, $username, $password) {
        // Validate inputs
        if (empty($fullname) || empty($email) || empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'تمام فیلدها الزامی هستند'];
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'فرمت ایمیل نامعتبر است'];
        }
        
        // Validate username (alphanumeric and underscore only, min 3 chars)
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return ['success' => false, 'message' => 'نام کاربری باید 3-20 کاراکتر و شامل حروف، اعداد و _ باشد'];
        }
        
        // Validate password strength (min 6 chars)
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'رمز عبور باید حداقل 6 کاراکتر باشد'];
        }
        
        // Check if username already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'این نام کاربری قبلاً استفاده شده است'];
        }
        
        // Check if email already exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'این ایمیل قبلاً ثبت‌نام شده است'];
        }
        
        try {
            // Create verification token
            $verificationToken = bin2hex(random_bytes(32));
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert user into database
            $stmt = $this->db->prepare("
                INSERT INTO users(fullname, email, username, password, role, is_verified, verification_token, created_at)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $fullname,
                $email,
                $username,
                $passwordHash,
                'teacher',
                0, // Not verified yet
                $verificationToken,
                date('Y-m-d H:i:s')
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Send verification email
            $verificationLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify-email.php?token=" . $verificationToken;
            
            $emailSent = $this->sendVerificationEmail($email, $fullname, $verificationLink);
            
            if ($emailSent) {
                return [
                    'success' => true,
                    'message' => 'ثبت‌نام موفق! لطفا ایمیل خود را بررسی کنید و بر روی لینک تأیید کلیک کنید',
                    'user_id' => $userId
                ];
            } else {
                return [
                    'success' => true,
                    'message' => 'ثبت‌نام موفق! لطفا ایمیل خود را بررسی کنید (ممکن است در Spam باشد)',
                    'user_id' => $userId,
                    'warning' => 'ایمیل تأیید به طور خودکار ارسال نشد'
                ];
            }
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'خطا در ثبت‌نام: ' . $e->getMessage()];
        }
    }
    
    /**
     * Verify email using token
     * Returns: ['success' => bool, 'message' => string]
     */
    public function verifyEmail($token) {
        if (empty($token)) {
            return ['success' => false, 'message' => 'توکن تأیید نامعتبر است'];
        }
        
        try {
            // Find user with this verification token
            $stmt = $this->db->prepare("SELECT id, is_verified FROM users WHERE verification_token = ?");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return ['success' => false, 'message' => 'توکن تأیید نامعتبر است'];
            }
            
            if ($user['is_verified']) {
                return ['success' => false, 'message' => 'این حساب قبلاً تأیید شده است'];
            }
            
            // Mark as verified
            $stmt = $this->db->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            return ['success' => true, 'message' => 'ایمیل شما با موفقیت تأیید شد! حالا می‌توانید وارد سیستم شوید'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'خطا در تأیید ایمیل: ' . $e->getMessage()];
        }
    }
    
    /**
     * Login user
     * Returns: ['success' => bool, 'message' => string, 'user' => array|null]
     */
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'نام کاربری و رمز عبور الزامی هستند'];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
            }
            
            // For admin user created with md5, check both password_hash and md5
            if (!password_verify($password, $user['password'])) {
                // Fallback to md5 for existing admin accounts
                if (md5($password) !== $user['password']) {
                    return ['success' => false, 'message' => 'نام کاربری یا رمز عبور اشتباه است'];
                }
            }
            
            // Check if email is verified (except for admin)
            if (!$user['is_verified'] && $user['username'] !== 'admin') {
                return [
                    'success' => false,
                    'message' => 'ایمیل شما هنوز تأیید نشده است. لطفا ایمیل خود را بررسی کنید',
                    'pending_verification' => true
                ];
            }
            
            return ['success' => true, 'message' => 'ورود موفق', 'user' => $user];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'خطا در ورود: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send verification email
     * Returns: bool
     */
    private function sendVerificationEmail($email, $fullname, $verificationLink) {
        $subject = "تأیید ایمیل - سیستم حضور و غیاب";
        $message = "
        <html>
            <body style='direction: rtl; font-family: Vazirmatn, Arial;'>
                <h2>سلام $fullname!</h2>
                <p>برای تأیید ایمیل خود و تکمیل ثبت‌نام، بر روی لینک زیر کلیک کنید:</p>
                <p>
                    <a href='$verificationLink' style='background-color: #6c63ff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>
                        تأیید ایمیل
                    </a>
                </p>
                <p>یا این لینک را در مرورگر خود کپی کنید:</p>
                <p>$verificationLink</p>
                <p>این لینک 24 ساعت معتبر است.</p>
            </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
        
        // Note: For production, use a proper email service like PHPMailer or SendGrid
        // For local development, you may need to use a local mail service
        return mail($email, $subject, $message, $headers);
    }
    
    /**
     * Get user by ID
     */
    public function getUserById($userId) {
        try {
            $stmt = $this->db->prepare("SELECT id, fullname, email, username, role, is_verified, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Update user password
     */
    public function updatePassword($userId, $currentPassword, $newPassword) {
        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => 'رمز عبور جدید باید حداقل 6 کاراکتر باشد'];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!password_verify($currentPassword, $user['password']) && md5($currentPassword) !== $user['password']) {
                return ['success' => false, 'message' => 'رمز عبور فعلی اشتباه است'];
            }
            
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$newPasswordHash, $userId]);
            
            return ['success' => true, 'message' => 'رمز عبور با موفقیت تغییر یافت'];
            
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }
}
?>
