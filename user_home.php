<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>JobHive | User Home</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <style>
    body {
      background: #f8fafc;
    }

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
      padding: 1.5rem 2rem;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: 1rem;
    }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: center;
      margin-top: 0.3rem;
    }

    .search-bar input[type="text"] {
      flex: 1 1 auto;
      min-width: 180px;
    }

    .search-bar select {
      min-width: 170px;
      max-width: 230px;
    }

    .btn-search {
      min-width: 110px;
      background: #ffc107;
      color: #fff;
      border: none;
      border-radius: 0.7rem;
      font-weight: 500;
      font-size: 1rem;
      padding: 0.45rem 0;
      transition: background 0.18s;
    }

    .btn-search:hover {
      background: #ff8800;
      color: #fff;
    }

    .popular-label {
      font-size: 1.12rem;
      color: #22223b;
      font-weight: 500;
      margin-right: 12px;
    }

    .popular-tags {
      margin-top: 1rem;
      display: flex;
      gap: 0.7rem;
      justify-content: center;
    }

    .popular-btn {
      border: 1.7px solid #ffc107;
      color: #ffc107;
      background: #fff;
      font-size: 1.01rem;
      border-radius: 0.55rem;
      padding: 0.33rem 1.25rem;
      font-weight: 500;
      transition: background 0.12s, color 0.12s;
    }

    .popular-btn:hover {
      background: #fff8ec;
      color: #ff8800;
      border-color: #ff8800;
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
  <!-- Navbar for logged-in user -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand fw-bold text-warning" href="home.php">JobHive</a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
        <ul class="navbar-nav">
          <li class="nav-item">
            <a class="nav-link active" aria-current="page" href="home.php">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="user_dashboard.php">Dashboard</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="recommended.php">Recommended Jobs</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="companies.php">All Companies</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-outline-warning ms-2" href="index.php">Logout</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="container">
      <h1 class="display-5 fw-bold">Welcome back to JobHive!</h1>
      <p class="lead mb-4">Ready to discover your next opportunity?</p>
      <form class="search-bar" autocomplete="off">
        <input class="form-control mb-2" type="text" placeholder="Job title, skills, company...">
        <div class="search-row">
          <select class="form-select" style="max-width:230px;">
            <option selected>All Locations</option>
            <option>Yangon</option>
            <option>Mandalay</option>
            <option>Naypyidaw</option>
          </select>
          <button class="btn btn-search" type="submit">Search</button>
        </div>
      </form>
      <!-- Popular tags/roles -->
      <div class="popular-tags">
        <span class="popular-label">Popular:</span>
        <button type="button" class="popular-btn">IT & Software</button>
        <button type="button" class="popular-btn">Engineering</button>
        <button type="button" class="popular-btn">Marketing</button>
        <button type="button" class="popular-btn">Finance</button>
      </div>
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