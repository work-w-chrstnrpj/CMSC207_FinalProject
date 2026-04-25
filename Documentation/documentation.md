# Sustainable Travel Planner - Project Documentation

## 1. Overview
The **Sustainable Travel Planner** is a PHP-based web application that helps users plan environmentally friendly trips and commute segments. It allows users to set up "Destination Goals" and map out the transportation modes to reach them, while computing an **Eco-Score** and an overall **Eco-Rating** for their travel based on the carbon emissions of the selected transport modes.

---

## 2. Core Features
- **User Authentication:** 
  - Secure registration (`register.php`) and login (`login.php`) systems.
  - Session-based user tracking.
- **Destination Goals:** 
  - Create and manage travel destinations (`destination_form.php`, `destination_detail.php`).
  - Destinations are displayed elegantly on the dashboard (`index.php`) with optional cover photos.
- **Trip Segments & Planning:**
  - Map out individual travel legs in `planner.php`.
  - Add specific transport modes (e.g., Walking, Bike, Train, Bus, Flight).
  - Segments define if the user is traveling *to* the destination or navigating *inside* the destination.
- **Eco-Rating System:**
  - Transport modes have associated emission factors. For instance, "Walking" and "Bike" have 0.00 factors and are labeled eco-friendly.
  - `travel_calculate_eco_rating` evaluates how "green" the travel profile is (scale of 1.0 to 5.0) by taking into account the average carbon estimations and the ratio of eco-friendly transport segments used.
- **Photo Management:**
  - Upload destination placeholder photos natively.

---

## 3. Tech Stack
- **Frontend:** HTML5, CSS3 (`style.css` with a responsive grid layout)
- **Backend:** PHP 8+
- **Database:** MySQL (`eco_travel`), managed via `db.php`
- **Host Environment:** XAMPP (Apache + MariaDB)

---

## 4. File Structure
- `index.php`: User Dashboard displaying destination goals.
- `login.php` & `register.php`: Authentication modules.
- `logout.php`: Clears active sessions securely.
- `planner.php` / `trip_edit.php`: Forms where users define travel modes and itinerary.
- `destination_*.php`: Modules for managing the creation, details, notes, and photos for destinations.
- `trip_segment_helper.php` & `trip_handler.php`: Background APIs and business logic governing eco-friendly calculation, segment ranking, schemas, and transport option data.
- `db.php`: Singleton database connectivity configuration.
- `eco_travel.sql`: Exported SQL database schema to rapidly seed a fresh clone of this project.

---

## 5. Usage / Setup Guidelines
1. Drop the project folder `sustainable_travel` into the `c:\xampp\htdocs` directory.
2. Launch the **XAMPP Control Panel** and ensure both **Apache** and **MySQL** are running.
3. Open `http://localhost/phpmyadmin` and import the `eco_travel.sql` database dump.
4. Visit `http://localhost/sustainable_travel` in your browser.
5. Register a new user account to access the dashboard.

---

*Documentation Generated — April 2026*