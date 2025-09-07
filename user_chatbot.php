<?php
// Enhanced rule-based chatbot for JobHive with button support
header('Content-Type: application/json');
// Get the user message
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
// Default response
$response = "I'm not sure how to respond to that. You can ask me about jobs, applications, profiles, or account settings.";
$buttons = []; // For button responses
// Convert to lowercase for easier matching
$lowerMessage = strtolower($message);
// Enhanced keyword matching with more specific responses
if (strpos($lowerMessage, 'hello') !== false || strpos($lowerMessage, 'hi') !== false || strpos($lowerMessage, 'hey') !== false || strpos($lowerMessage, 'greetings') !== false) {
    $response = "Hello! I'm your JobHive assistant. How can I help you with your job search today?";
}
// Home
elseif (strpos($lowerMessage, 'home') !== false) {
    $response = "The home page is where you can search for jobs, view featured job listings, and access all the main features of JobHive. It's your starting point for finding your next career opportunity.";
    $buttons = [
        ['text' => 'Go to Home', 'href' => 'user_home.php']
    ];
}
// Login
elseif (strpos($lowerMessage, 'login') !== false) {
    $response = "You can log in to your JobHive account using your email and password. If you've forgotten your password, you can reset it from the login page.";
    $buttons = [
        ['text' => 'Go to Login', 'href' => 'login.php']
    ];
}
// Register
elseif (strpos($lowerMessage, 'register') !== false || strpos($lowerMessage, 'sign up') !== false) {
    $response = "Create a JobHive account to start applying for jobs. Registration is quick and easy - just provide your basic information and you'll be ready to start your job search.";
    $buttons = [
        ['text' => 'Register Now', 'href' => 'sign_up.php']
    ];
}
// Company Register
elseif (strpos($lowerMessage, 'company register') !== false || strpos($lowerMessage, 'company sign up') !== false) {
    $response = "If you're an employer, you can register your company on JobHive to post job listings and find qualified candidates. Create a company account to start hiring today.";
    $buttons = [
        ['text' => 'Register Company', 'href' => 'c_sign_up.php']
    ];
}
// Logout
elseif (strpos($lowerMessage, 'logout') !== false || strpos($lowerMessage, 'log out') !== false || strpos($lowerMessage, 'sign out') !== false) {
    $response = "You can log out of your JobHive account by clicking the logout button. This will securely end your current session.";
    $buttons = [
        ['text' => 'Logout Now', 'href' => 'logout.php']
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
// Dashboard
elseif (strpos($lowerMessage, 'dashboard') !== false) {
    $response = "Your dashboard is where you can track all your applications, view your profile completion, and see recommended jobs based on your profile. It's your personal control center for managing your job search activities.";
    $buttons = [
        ['text' => 'Go to Dashboard', 'href' => 'user_dashboard.php']
    ];
}
// All Companies
elseif (strpos($lowerMessage, 'all companies') !== false) {
    $response = "You can view all companies by clicking the 'All Companies' button in the navigation menu.";
    $buttons = [
        ['text' => 'View All Companies', 'href' => 'all_companies.php']
    ];
}
// Profile
elseif (strpos($lowerMessage, 'profile') !== false) {
    $response = "Your profile contains all your personal information, work experience, education, and skills. A complete profile increases your chances of getting hired by employers.";
    $buttons = [
        ['text' => 'View My Profile', 'href' => 'user_profile.php']
    ];
}
// Job Search Keywords
elseif (strpos($lowerMessage, 'job') !== false || strpos($lowerMessage, 'position') !== false || strpos($lowerMessage, 'vacancy') !== false || strpos($lowerMessage, 'opening') !== false) {
    if (strpos($lowerMessage, 'find') !== false || strpos($lowerMessage, 'search') !== false || strpos($lowerMessage, 'look') !== false) {
        $response = "You can search for jobs using the search bar on the home page. You can filter by location and job type to find positions that match your skills.";
    } elseif (strpos($lowerMessage, 'software') !== false || strpos($lowerMessage, 'it') !== false || strpos($lowerMessage, 'tech') !== false) {
        $response = "We have many software job listings available. Use the search feature and select 'Software' as the job type to find relevant positions.";
    } elseif (strpos($lowerMessage, 'network') !== false || strpos($lowerMessage, 'networking') !== false) {
        $response = "For network-related jobs, use the search feature and select 'Network' as the job type. We have listings from various companies.";
    } elseif (strpos($lowerMessage, 'location') !== false || strpos($lowerMessage, 'city') !== false || strpos($lowerMessage, 'place') !== false) {
        $response = "You can filter jobs by location using the dropdown menu in the search bar. We have listings in Yangon, Mandalay, Naypyidaw, and other cities.";
    } else {
        $response = "We have many job listings available. Use the search feature to find positions that match your skills and preferences. You can filter by job type and location.";
    }
}
// Application Keywords
elseif (strpos($lowerMessage, 'application') !== false || strpos($lowerMessage, 'apply') !== false || strpos($lowerMessage, 'applied') !== false) {
    if (strpos($lowerMessage, 'track') !== false || strpos($lowerMessage, 'status') !== false || strpos($lowerMessage, 'check') !== false) {
        $response = "You can track your applications by going to your dashboard. There you'll see the status of all your applications and any updates from employers.";
        $buttons = [
            ['text' => 'Go to Dashboard', 'href' => 'user_dashboard.php']
        ];
    } elseif (strpos($lowerMessage, 'how') !== false || strpos($lowerMessage, 'process') !== false) {
        $response = "To apply for a job: 1) Find a job you're interested in, 2) Click on 'Detail' to see more information, 3) Follow the application instructions provided by the employer.";
    } else {
        $response = "To apply for a job, click on the job details and follow the application instructions. You can track all your applications in your dashboard.";
        $buttons = [
            ['text' => 'Go to Dashboard', 'href' => 'user_dashboard.php']
        ];
    }
}
// Premium Keywords
elseif (strpos($lowerMessage, 'premium') !== false || strpos($lowerMessage, 'membership') !== false || strpos($lowerMessage, 'upgrade') !== false) {
    if (strpos($lowerMessage, 'price') !== false || strpos($lowerMessage, 'cost') !== false || strpos($lowerMessage, 'fee') !== false) {
        $response = "Premium membership is currently 30,000 MMK (40% off the regular price of 50,000 MMK). You get access to advanced resume templates and auto-fill features.";
    } elseif (strpos($lowerMessage, 'benefit') !== false || strpos($lowerMessage, 'feature') !== false || strpos($lowerMessage, 'advantage') !== false) {
        $response = "Premium benefits include: 1) Beautiful ATS-friendly resume templates, 2) One-click auto-fill from your profile, 3) Download resumes as PDF/PNG, 4) Priority application processing.";
    } elseif (strpos($lowerMessage, 'how') !== false || strpos($lowerMessage, 'get') !== false) {
        $response = "To upgrade to Premium: 1) Make sure your profile is 100% complete, 2) Click the 'Go Premium' button, 3) Follow the payment instructions.";
    } else {
        $response = "Premium membership gives you access to advanced resume templates and auto-fill features. Click the 'Go Premium' button to learn more about the benefits and pricing.";
        $buttons = [
            ['text' => 'Go Premium', 'href' => 'premium.php']
        ];
    }
}
// Profile Update Keywords
elseif (strpos($lowerMessage, 'update') !== false || strpos($lowerMessage, 'edit') !== false || strpos($lowerMessage, 'change') !== false) {
    $response = "You can update your profile by clicking on your name in the top right corner and selecting 'My Profile'. Make sure all your information is complete for the best results.";
    $buttons = [
        ['text' => 'Update Profile', 'href' => 'user_profile.php']
    ];
} elseif (strpos($lowerMessage, 'complete') !== false || strpos($lowerMessage, 'missing') !== false) {
    $response = "To complete your profile, go to 'My Profile' and fill in all required fields: full name, email, password, gender, education, phone, address, birth date, job category, current position, and profile picture.";
    $buttons = [
        ['text' => 'Complete Profile', 'href' => 'user_profile.php']
    ];
} elseif (strpos($lowerMessage, 'picture') !== false || strpos($lowerMessage, 'photo') !== false || strpos($lowerMessage, 'avatar') !== false) {
    $response = "To add or change your profile picture, go to 'My Profile' and upload a new image. A professional photo helps employers recognize you.";
    $buttons = [
        ['text' => 'Update Profile Picture', 'href' => 'user_profile.php']
    ];
}
// Notification Keywords
elseif (strpos($lowerMessage, 'notification') !== false || strpos($lowerMessage, 'alert') !== false || strpos($lowerMessage, 'message') !== false) {
    $response = "You can see your notifications by clicking the envelope icon in the top right corner. We'll notify you about application updates, new job matches, and important account information.";
}
// Resume Keywords
elseif (strpos($lowerMessage, 'resume') !== false || strpos($lowerMessage, 'cv') !== false || strpos($lowerMessage, 'curriculum') !== false) {
    if (strpos($lowerMessage, 'template') !== false || strpos($lowerMessage, 'design') !== false) {
        $response = "Premium members have access to beautiful, ATS-friendly resume templates. These templates are designed to help your resume pass through automated screening systems.";
    } elseif (strpos($lowerMessage, 'download') !== false || strpos($lowerMessage, 'save') !== false || strpos($lowerMessage, 'export') !== false) {
        $response = "Premium members can download their resumes as PDF or PNG files. This makes it easy to share your resume with employers or upload to other job sites.";
    } elseif (strpos($lowerMessage, 'auto') !== false || strpos($lowerMessage, 'fill') !== false || strpos($lowerMessage, 'automatic') !== false) {
        $response = "Premium members can use the auto-fill feature to populate their resume with information from their JobHive profile. This saves time and ensures consistency.";
    } else {
        $response = "With Premium membership, you get access to professional resume templates and auto-fill features. These tools help you create a standout resume quickly and easily.";
        $buttons = [
            ['text' => 'Go Premium', 'href' => 'premium.php']
        ];
    }
}
// Interview Keywords
elseif (strpos($lowerMessage, 'interview') !== false || strpos($lowerMessage, 'meeting') !== false || strpos($lowerMessage, 'talk') !== false) {
    $response = "While we don't directly schedule interviews, we'll notify you when an employer wants to interview you. Make sure your profile is complete and your contact information is up to date.";
}
// Salary Keywords
elseif (strpos($lowerMessage, 'salary') !== false || strpos($lowerMessage, 'pay') !== false || strpos($lowerMessage, 'income') !== false || strpos($lowerMessage, 'wage') !== false) {
    $response = "Salary information is typically included in the job details. If it's not listed, you can discuss compensation during the interview process or contact the employer directly.";
}
// Company Keywords
elseif (strpos($lowerMessage, 'company') !== false || strpos($lowerMessage, 'employer') !== false || strpos($lowerMessage, 'business') !== false) {
    $response = "You can browse all companies on our platform by clicking 'All Companies' in the navigation menu. There you'll find company profiles and their current job openings.";
    $buttons = [
        ['text' => 'Browse Companies', 'href' => 'all_companies.php']
    ];
}
// Help/Support Keywords
elseif (strpos($lowerMessage, 'help') !== false || strpos($lowerMessage, 'support') !== false || strpos($lowerMessage, 'assistance') !== false) {
    $response = "I'm here to help! You can ask me about jobs, applications, your profile, premium features, or account settings. For more detailed assistance, please contact our support team at support@jobhive.mm.";
}
// Thank You Keywords
elseif (strpos($lowerMessage, 'thank') !== false || strpos($lowerMessage, 'thanks') !== false) {
    $response = "You're welcome! Is there anything else I can help you with?";
}
// Goodbye Keywords
elseif (strpos($lowerMessage, 'bye') !== false || strpos($lowerMessage, 'goodbye') !== false || strpos($lowerMessage, 'farewell') !== false) {
    $response = "Goodbye! Feel free to come back if you have more questions. Happy job hunting!";
}
// Return the response as JSON
echo json_encode(['response' => $response, 'buttons' => $buttons]);
