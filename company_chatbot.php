<?php
// Enhanced rule-based chatbot for JobHive Companies with button support
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
// Check if company is logged in
$company_id = $_SESSION['company_id'] ?? null;
if (!$company_id) {
    echo json_encode(['response' => 'Please log in to use the chatbot.', 'buttons' => [['text' => 'Go to Login', 'href' => 'login.php']]]);
    exit;
}
// Get the user message
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
// Default response
$response = "I'm here to help with your company account. You can ask me about posting jobs, managing applications, membership benefits, or company settings.";
$buttons = []; // For button responses
// Convert to lowercase for easier matching
$lowerMessage = strtolower($message);
// Enhanced keyword matching with more specific responses
if (strpos($lowerMessage, 'hello') !== false || strpos($lowerMessage, 'hi') !== false || strpos($lowerMessage, 'hey') !== false || strpos($lowerMessage, 'greetings') !== false) {
    $response = "Hello! I'm your JobHive company assistant. How can I help you with your recruitment needs today?";
}
// Home
elseif (strpos($lowerMessage, 'home') !== false) {
    $response = "The company home page is where you can search your job postings, view featured jobs, and access all the main features of JobHive for companies.";
    $buttons = [
        ['text' => 'Go to Home', 'href' => 'company_home.php']
    ];
}
// Dashboard
elseif (strpos($lowerMessage, 'dashboard') !== false) {
    $response = "Your company dashboard is where you can track all your job postings, view application statistics, and manage your recruitment activities.";
    $buttons = [
        ['text' => 'Go to Dashboard', 'href' => 'c_dashboard.php']
    ];
}
// FAQ
elseif (strpos($lowerMessage, 'faq') !== false || strpos($lowerMessage, 'frequently asked questions') !== false) {
    $response = "Check our Frequently Asked Questions section for answers to common questions about using JobHive, finding jobs, and managing your account.";
    $buttons = [
        ['text' => 'View FAQ', 'href' => 'faq.php']
    ];
}
// About Us
elseif (strpos($lowerMessage, 'about us') !== false || strpos($lowerMessage, 'about') !== false) {
    $response = "Learn more about JobHive, our mission, and our team. We're dedicated to connecting job seekers with great opportunities and helping companies find talented professionals.";
    $buttons = [
        ['text' => 'About JobHive', 'href' => 'about.php']
    ];
}
// Privacy Policy
elseif (strpos($lowerMessage, 'privacy policy') !== false || strpos($lowerMessage, 'privacy') !== false) {
    $response = "Read our Privacy Policy to understand how we collect, use, and protect your personal information. Your privacy is important to us.";
    $buttons = [
        ['text' => 'View Privacy Policy', 'href' => 'privacy.php']
    ];
}
// Terms & Conditions
elseif (strpos($lowerMessage, 'terms & conditions') !== false || strpos($lowerMessage, 'terms') !== false || strpos($lowerMessage, 'conditions') !== false) {
    $response = "Review our Terms & Conditions to understand the rules and guidelines for using JobHive. These terms govern your use of our platform and services.";
    $buttons = [
        ['text' => 'View Terms & Conditions', 'href' => 'terms.php']
    ];
}
// Post Job
elseif (strpos($lowerMessage, 'post') !== false && strpos($lowerMessage, 'job') !== false) {
    if (strpos($lowerMessage, 'how') !== false || strpos($lowerMessage, 'process') !== false) {
        $response = "To post a job: 1) Click the 'Post Job' button in the navigation menu, 2) Fill in all required job details, 3) Review and publish your job listing.";
    } elseif (strpos($lowerMessage, 'fee') !== false || strpos($lowerMessage, 'price') !== false || strpos($lowerMessage, 'cost') !== false) {
        $response = "Job posting fees vary based on your membership tier. The more jobs you post, the more you save with volume-based discounts.";
        $buttons = [
            ['text' => 'View Membership', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnMember").click()']
        ];
    } else {
        $response = "You can post a new job by clicking the 'Post Job' button in the navigation menu. Make sure to provide detailed job information to attract qualified candidates.";
        $buttons = [
            ['text' => 'Post a Job', 'href' => 'post_job.php']
        ];
    }
}
// Applications
elseif (strpos($lowerMessage, 'application') !== false || strpos($lowerMessage, 'applicant') !== false) {
    if (strpos($lowerMessage, 'view') !== false || strpos($lowerMessage, 'check') !== false || strpos($lowerMessage, 'see') !== false) {
        $response = "You can view all applications by clicking the envelope icon in the navigation bar. There you'll see all candidates who have applied to your job postings.";
        $buttons = [
            ['text' => 'View Applications', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnInbox").click()']
        ];
    } elseif (strpos($lowerMessage, 'manage') !== false || strpos($lowerMessage, 'status') !== false) {
        $response = "You can manage application statuses by viewing each application detail. You can mark applications as read, accepted, or rejected.";
        $buttons = [
            ['text' => 'View Applications', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnInbox").click()']
        ];
    } elseif (strpos($lowerMessage, 'contact') !== false || strpos($lowerMessage, 'email') !== false || strpos($lowerMessage, 'phone') !== false) {
        $response = "You can contact applicants directly through their provided contact information in the application details. We recommend using professional communication channels.";
        $buttons = [
            ['text' => 'View Applications', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnInbox").click()']
        ];
    } else {
        $response = "To manage applications, click the envelope icon in the navigation bar. You can view all applications, mark them as read, and contact candidates directly.";
        $buttons = [
            ['text' => 'View Applications', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnInbox").click()']
        ];
    }
}
// Membership
elseif (strpos($lowerMessage, 'membership') !== false || strpos($lowerMessage, 'tier') !== false || strpos($lowerMessage, 'premium') !== false) {
    if (strpos($lowerMessage, 'benefit') !== false || strpos($lowerMessage, 'feature') !== false || strpos($lowerMessage, 'advantage') !== false) {
        $response = "Membership benefits include volume-based discounts: 10% off after 5 posts, 15% off after 15 posts, and 20% off after 25 posts. Higher tiers also get more visibility for their job postings.";
    } elseif (strpos($lowerMessage, 'upgrade') !== false || strpos($lowerMessage, 'how') !== false) {
        $response = "Your membership tier automatically upgrades as you post more jobs. There's no separate upgrade process - just keep posting quality jobs to enjoy better rates.";
    } elseif (strpos($lowerMessage, 'current') !== false || strpos($lowerMessage, 'my') !== false) {
        $response = "You can check your current membership tier by clicking on your company name in the top-right corner. Your tier and post count are displayed there.";
        $buttons = [
            ['text' => 'View Membership', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnMember").click()']
        ];
    } else {
        $response = "JobHive offers membership tiers with increasing benefits as you post more jobs. Click the 'Membership' button to see your current tier and benefits.";
        $buttons = [
            ['text' => 'View Membership', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnMember").click()']
        ];
    }
}
// Company Profile
elseif (strpos($lowerMessage, 'profile') !== false || strpos($lowerMessage, 'account') !== false) {
    if (strpos($lowerMessage, 'edit') !== false || strpos($lowerMessage, 'update') !== false || strpos($lowerMessage, 'change') !== false) {
        $response = "You can edit your company profile by clicking on your company name in the top-right corner and selecting 'Profile'. Keep your information up to date to attract more candidates.";
        $buttons = [
            ['text' => 'Edit Profile', 'href' => 'company_profile.php']
        ];
    } elseif (strpos($lowerMessage, 'logo') !== false || strpos($lowerMessage, 'image') !== false || strpos($lowerMessage, 'picture') !== false) {
        $response = "To update your company logo, go to your company profile and upload a new image. A professional logo helps build brand recognition with candidates.";
        $buttons = [
            ['text' => 'Edit Profile', 'href' => 'company_profile.php']
        ];
    } else {
        $response = "Your company profile contains information about your business, which helps candidates learn more about your company. Keep it complete and up to date.";
        $buttons = [
            ['text' => 'View Profile', 'href' => 'company_profile.php']
        ];
    }
}
// Job Management
elseif (strpos($lowerMessage, 'job') !== false) {
    if (strpos($lowerMessage, 'manage') !== false || strpos($lowerMessage, 'edit') !== false || strpos($lowerMessage, 'update') !== false) {
        $response = "You can manage your job postings from the company home page or dashboard. There you can edit job details, change status, or view application statistics.";
        $buttons = [
            ['text' => 'View Jobs', 'href' => 'company_home.php']
        ];
    } elseif (strpos($lowerMessage, 'status') !== false || strpos($lowerMessage, 'active') !== false || strpos($lowerMessage, 'inactive') !== false || strpos($lowerMessage, 'close') !== false) {
        $response = "You can change the status of your job postings (Active, Inactive, Closed) from the job detail page. This helps you control when candidates can apply to your positions.";
        $buttons = [
            ['text' => 'View Jobs', 'href' => 'company_home.php']
        ];
    } elseif (strpos($lowerMessage, 'statistics') !== false || strpos($lowerMessage, 'stats') !== false || strpos($lowerMessage, 'performance') !== false) {
        $response = "View job statistics on your dashboard to see how many people have viewed and applied to your postings. This helps you understand which jobs are performing well.";
        $buttons = [
            ['text' => 'Go to Dashboard', 'href' => 'c_dashboard.php']
        ];
    } else {
        $response = "You can manage all your job postings from the company home page. There you can view, edit, and monitor the performance of your job listings.";
        $buttons = [
            ['text' => 'View Jobs', 'href' => 'company_home.php']
        ];
    }
}
// Feedback Keywords - MOVED BEFORE PRICING TO AVOID CONFLICT
elseif (strpos($lowerMessage, 'feedback') !== false) {
    $response = "We value your feedback! As a company partner, your input helps us improve JobHive for both employers and job seekers. You can share your thoughts or view feedback from others.";
    $buttons = [
        ['text' => 'Send Feedback', 'href' => 'company_home.php#feedbackModal'],
        ['text' => 'View Feedback', 'href' => 'company_home.php#feedback']
    ];
}
// Pricing - MOVED AFTER FEEDBACK
elseif (strpos($lowerMessage, 'pricing') !== false || strpos($lowerMessage, 'fee') !== false || strpos($lowerMessage, 'cost') !== false || strpos($lowerMessage, 'price') !== false) {
    $response = "Job posting fees start at a base rate and decrease as you post more jobs. You get 10% off after 5 posts, 15% off after 15 posts, and 20% off after 25 posts.";
    $buttons = [
        ['text' => 'View Membership', 'href' => 'javascript:void(0)', 'onclick' => 'document.getElementById("btnMember").click()']
    ];
}
// Logout
elseif (strpos($lowerMessage, 'logout') !== false || strpos($lowerMessage, 'log out') !== false || strpos($lowerMessage, 'sign out') !== false) {
    $response = "You can log out of your JobHive company account by clicking the logout button. This will securely end your current session.";
    $buttons = [
        ['text' => 'Logout Now', 'href' => 'logout.php']
    ];
}
// Help/Support Keywords
elseif (strpos($lowerMessage, 'help') !== false || strpos($lowerMessage, 'support') !== false || strpos($lowerMessage, 'assistance') !== false) {
    $response = "I'm here to help! You can ask me about posting jobs, managing applications, membership benefits, or company settings. For more detailed assistance, please contact our support team at support@jobhive.mm.";
}
// Thank You Keywords
elseif (strpos($lowerMessage, 'thank') !== false || strpos($lowerMessage, 'thanks') !== false) {
    $response = "You're welcome! Is there anything else I can help you with?";
}
// Goodbye Keywords
elseif (strpos($lowerMessage, 'bye') !== false || strpos($lowerMessage, 'goodbye') !== false || strpos($lowerMessage, 'farewell') !== false) {
    $response = "Goodbye! Feel free to come back if you have more questions. Happy hiring!";
}
// Return the response as JSON
echo json_encode(['response' => $response, 'buttons' => $buttons]);
