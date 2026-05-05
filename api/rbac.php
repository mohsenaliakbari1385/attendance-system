<?php
/**
 * Role-Based Access Control (RBAC)
 * Handles authorization and access control
 */

class RBAC {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated() {
        return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }
    
    /**
     * Check if user has a specific role
     */
    public static function hasRole($role) {
        if (!self::isAuthenticated()) {
            return false;
        }
        return $_SESSION['user']['role'] === $role;
    }
    
    /**
     * Check if user is teacher
     */
    public static function isTeacher() {
        return self::hasRole('teacher');
    }
    
    /**
     * Check if user is student
     */
    public static function isStudent() {
        return self::hasRole('student');
    }
    
    /**
     * Check if email is verified
     */
    public static function isEmailVerified() {
        if (!self::isAuthenticated()) {
            return false;
        }
        return !empty($_SESSION['user']['is_verified']);
    }
    
    /**
     * Require authentication - redirect if not authenticated
     */
    public static function requireAuth() {
        if (!self::isAuthenticated()) {
            header("Location: " . dirname($_SERVER['PHP_SELF']) . "/index.php");
            exit;
        }
    }
    
    /**
     * Require specific role - redirect if not authorized
     */
    public static function requireRole($role) {
        self::requireAuth();
        if (!self::hasRole($role)) {
            header("Location: " . dirname($_SERVER['PHP_SELF']) . "/index.php");
            exit;
        }
    }
    
    /**
     * Require teacher role
     */
    public static function requireTeacher() {
        self::requireRole('teacher');
    }
    
    /**
     * Require student role
     */
    public static function requireStudent() {
        self::requireRole('student');
    }
    
    /**
     * Get current user ID
     */
    public static function getCurrentUserId() {
        if (self::isAuthenticated()) {
            return $_SESSION['user']['id'];
        }
        return null;
    }
    
    /**
     * Get current user
     */
    public static function getCurrentUser() {
        if (self::isAuthenticated()) {
            return $_SESSION['user'];
        }
        return null;
    }
    
    /**
     * Check if student can access their own data
     * Returns true if the requested user_id is the current student or if user is teacher
     */
    public static function canAccessStudentData($targetStudentId) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        // Teachers can access all student data
        if (self::isTeacher()) {
            return true;
        }
        
        // Students can only access their own data
        if (self::isStudent()) {
            return self::getCurrentUserId() === (int)$targetStudentId;
        }
        
        return false;
    }
    
    /**
     * Check if student can perform restricted action (like marking attendance)
     * Only the student themselves can mark their own attendance
     */
    public static function canMarkAttendance($studentId) {
        // Teachers cannot mark attendance (only students can)
        if (self::isTeacher()) {
            return false;
        }
        
        // Students can only mark their own attendance
        if (self::isStudent()) {
            return self::getCurrentUserId() === (int)$studentId;
        }
        
        return false;
    }
    
    /**
     * Check if user can modify another user (create/edit/delete)
     */
    public static function canModifyUser($targetUserId) {
        if (!self::isAuthenticated()) {
            return false;
        }
        
        // Only teachers can modify users
        if (!self::isTeacher()) {
            return false;
        }
        
        // Teachers cannot modify other teachers
        $stmt = $this->db->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();
        
        return $targetUser && $targetUser['role'] !== 'teacher';
    }
    
    /**
     * Check if user can create courses/classes
     */
    public static function canManageCourses() {
        return self::isTeacher();
    }
    
    /**
     * Check if user can export data
     */
    public static function canExportData() {
        return self::isTeacher();
    }
    
    /**
     * Log security event (optional, for audit trails)
     */
    public static function logSecurityEvent($action, $details = "") {
        $userId = self::getCurrentUserId() ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $timestamp = date('Y-m-d H:i:s');
        
        // You can store this in a database table for auditing
        // Example: INSERT INTO security_logs (user_id, action, details, ip, timestamp) VALUES (...)
        
        error_log("[$timestamp] Action: $action | User: $userId | IP: $ip | Details: $details");
    }
}
?>
