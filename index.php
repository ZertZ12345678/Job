<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>JobHive | Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>

    
    .hero-section {
      background: #f8fafc;
      padding: 70px 0 50px 0;
      text-align: center;
    }

    .search-bar {
      max-width: 700px;
      margin: 0 auto;
      margin-top: 30px;
      box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
      border-radius: 1.5rem;
      background: #fff;
      padding: 1.5rem;
    }

    .job-card {
      border-radius: 1.5rem;
      box-shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
      margin-bottom: 2rem;
    }

    .company-logo {
      height: 48px;
      width: auto;
      margin-right: 10px;
      object-fit: contain;
    }

    .how-works-icon {
      font-size: 2.5rem;
      color: #ffaa2b;
    }

    .footer {
      background: #1a202c;
      color: #fff;
      padding: 30px 0 10px 0;
      text-align: center;
    }
  </style>
</head>

<body>
  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="#">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item"><a class="nav-link" href="#">Jobs</a></li>
          <li class="nav-item"><a class="nav-link" href="#">Companies</a></li>
          <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
          <li class="nav-item">
            <a class="btn btn-warning ms-2 text-white" href="sign_up.php">Register</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-outline-warning ms-2" href="c_sign_up.php" style="border-width:2px;">Company Register</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-5 fw-bold">Find Your Dream Job on JobHive</h1>
      <p class="lead mb-4">Connecting you to thousands of opportunities across Myanmar.</p>
      <form class="search-bar d-flex flex-wrap gap-2">
        <input class="form-control flex-grow-1" type="text" placeholder="Job title, skills, company...">
        <select class="form-select flex-grow-1" style="max-width: 180px;">
          <option selected>All Locations</option>
          <option>Yangon</option>
          <option>Mandalay</option>
          <option>Naypyidaw</option>
        </select>
        <button class="btn btn-warning px-4 text-white" type="submit">Search</button>
      </form>
      <div class="mt-4">
        <span class="me-2">Popular:</span>
        <a href="#" class="btn btn-sm btn-outline-warning me-1 mb-1">IT & Software</a>
        <a href="#" class="btn btn-sm btn-outline-warning me-1 mb-1">Engineering</a>
        <a href="#" class="btn btn-sm btn-outline-warning me-1 mb-1">Marketing</a>
        <a href="#" class="btn btn-sm btn-outline-warning me-1 mb-1">Finance</a>
      </div>
    </div>
  </section>

  <!-- Featured Jobs -->
  <section class="container py-5">
    <h2 class="mb-4 fw-semibold text-center">Featured Jobs</h2>
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <!--  Job Card -->
      <div class="col">
        <div class="card job-card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <img src="company_logos\logo_688bd678d33ad.png" class="company-logo" alt="Company Logo">
              <div>
                <h5 class="card-title mb-0">Software Engineer</h5>
                <small class="text-muted">AYA Bank</small>
              </div>
            </div>
            <div class="mb-2"><span class="badge bg-light text-dark">Hleden, Yangon</span></div>
            <p class="card-text">Build and maintain web applications with a dynamic team.</p>
            <a href="#" class="btn btn-outline-warning btn-sm">Apply</a>
          </div>
        </div>
      </div>
      <!-- Repeat .col for more jobs -->
      <div class="col">
        <div class="card job-card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <img src="company_logos\logo_688cf808f216b.png" class="company-logo" alt="Company Logo">
              <div>
                <h5 class="card-title mb-0">Marketing Specialist</h5>
                <small class="text-muted">City Mark</small>
              </div>
            </div>
            <div class="mb-2"><span class="badge bg-light text-dark">Ahlone, Yangon</span></div>
            <p class="card-text">Plan and execute marketing campaigns across channels.</p>
            <a href="#" class="btn btn-outline-warning btn-sm">Apply</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card job-card h-100">
          <div class="card-body">
            <div class="d-flex align-items-center mb-2">
              <img src="company_logos\logo_688bd4015182f.png" class="company-logo" alt="Company Logo">
              <div>
                <h5 class="card-title mb-0">Accountant</h5>
                <small class="text-muted">KBZ Bank</small>
              </div>
            </div>
            <div class="mb-2"><span class="badge bg-light text-dark">Ahlone, Yangon</span></div>
            <p class="card-text">Manage financial records and prepare monthly reports.</p>
            <a href="#" class="btn btn-outline-warning btn-sm">Apply</a>
          </div>
        </div>
      </div>
    </div>
  </section>




  <!-- How it Works -->
  <section class="bg-light py-5">
    <div class="container">
      <h2 class="text-center mb-5 fw-semibold">How JobHive Works</h2>
      <div class="row text-center">
        <div class="col-md-4">
          <div class="how-works-icon mb-2">🔎</div>
          <h5>1. Search Jobs</h5>
          <p>Browse thousands of jobs by title, skill, or location to find your fit.</p>
        </div>
        <div class="col-md-4">
          <div class="how-works-icon mb-2">📄</div>
          <h5>2. Apply Online</h5>
          <p>Apply to jobs easily with your JobHive profile and resume.</p>
        </div>
        <div class="col-md-4">
          <div class="how-works-icon mb-2">💼</div>
          <h5>3. Get Hired</h5>
          <p>Connect with top companies and land your next career move.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Featured Companies -->
  <section class="container py-5">
    <h2 class="mb-4 fw-semibold text-center">Featured Companies</h2>
    <div class="d-flex flex-wrap justify-content-center gap-4">
      <img src="https://via.placeholder.com/90x40?text=ABC+Tech" class="company-logo" alt="ABC Tech">
      <img src="https://via.placeholder.com/90x40?text=Bright+Marketing" class="company-logo" alt="Bright Marketing">
      <img src="https://via.placeholder.com/90x40?text=Golden+Finance" class="company-logo" alt="Golden Finance">
      <img src="https://via.placeholder.com/90x40?text=NextGen" class="company-logo" alt="NextGen">
      <!-- Add more logos -->
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <div class="mb-2">
        <a href="#" class="text-white text-decoration-none me-3">About</a>
        <a href="#" class="text-white text-decoration-none me-3">Contact</a>
        <a href="#" class="text-white text-decoration-none">Privacy Policy</a>
      </div>
      <small>&copy; 2025 JobHive. All rights reserved.</small>
    </div>
  </footer>

</body>

</html>