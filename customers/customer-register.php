<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Registration</title>

  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      background: linear-gradient(120deg, #e5e7eb 60%, #1651c6 100%);
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .register-container {
      background: #ffffff;
      border-radius: 28px;
      box-shadow: 0 4px 25px rgba(0, 0, 0, 0.2);
      padding: 2.5rem;
      width: 100%;
      max-width: 420px;
      text-align: center;
    }

    .register-container h2 {
      color: #1651c6;
      font-size: 1.6rem;
      font-weight: bold;
      margin-bottom: 1.5rem;
      letter-spacing: 0.5px;
    }

    .register-form {
      display: flex;
      flex-direction: column;
      gap: 1rem;
      text-align: left;
    }

    .register-form label {
      font-weight: 600;
      color: #374151;
      font-size: 0.95rem;
      margin-bottom: 0.3rem;
    }

    .register-form input,
    .register-form select {
      width: 100%;
      padding: 0.85rem;
      font-size: 1rem;
      border: 2px solid #cbd5e1;
      border-radius: 10px;
      background: #f8fafc;
      transition: all 0.2s ease;
      font-family: inherit;
    }

    .register-form input:focus,
    .register-form select:focus {
      border-color: #1651c6;
      outline: none;
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(22, 81, 198, 0.1);
    }

    .input-error {
      border: 2px solid #ef4444 !important;
      background: #fee2e2 !important;
    }

    .register-btn {
      width: 100%;
      padding: 1rem;
      font-size: 1.1rem;
      font-weight: bold;
      color: white;
      background: linear-gradient(135deg, #1651c6 0%, #1e5bb8 100%);
      border: none;
      border-radius: 10px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(22, 81, 198, 0.3);
      transition: all 0.25s ease;
      margin-top: 0.5rem;
      letter-spacing: 0.5px;
    }

    .register-btn:hover {
      background: linear-gradient(135deg, #0d3b9c 0%, #1651c6 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(22, 81, 198, 0.4);
    }

    .register-btn:active {
      transform: translateY(0);
    }

    .register-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .back-link {
      width: 100%;
      padding: 0.85rem;
      font-size: 1rem;
      font-weight: 600;
      text-align: center;
      display: block;
      color: #1651c6;
      background: #e0e7ff;
      border: 2px solid #1651c6;
      border-radius: 10px;
      text-decoration: none;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
      transition: all 0.25s ease;
      margin-top: 1rem;
      letter-spacing: 0.3px;
    }

    .back-link:hover {
      background: #1651c6;
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(22, 81, 198, 0.3);
    }

    .back-link:active {
      transform: translateY(0);
    }

    .msg-success {
      color: #16a34a;
      background: #dcfce7;
      padding: 0.9rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      font-weight: 600;
      border: 2px solid #86efac;
    }

    .msg-error {
      color: #ef4444;
      background: #fee2e2;
      padding: 0.9rem;
      border-radius: 10px;
      margin-bottom: 1rem;
      font-weight: 600;
      border: 2px solid #fca5a5;
    }

    @media (max-width: 480px) {
      .register-container {
        padding: 2rem 1.5rem;
      }

      .register-container h2 {
        font-size: 1.4rem;
      }

      .register-form input,
      .register-form select {
        padding: 0.75rem;
        font-size: 0.95rem;
      }

      .register-btn {
        padding: 0.9rem;
        font-size: 1rem;
      }

      .back-link {
        padding: 0.75rem;
        font-size: 0.95rem;
      }
    }
  </style>

</head>
<body>

  <div class="register-container">
    <h2>Customer Registration</h2>

    <div id="message-container"></div>

    <form class="register-form" id="registerForm">

      <label for="name">Full Name*</label>
      <input type="text" id="name" name="name" placeholder="Enter your full name" required>

      <label for="email">Email Address*</label>
      <input type="email" id="email" name="email" placeholder="Enter your email" required>

      <label for="plate">Plate Number*</label>
      <input type="text" id="plate" name="plate" placeholder="e.g., ABC-1234" required>

      <label for="vehicle">Vehicle Type*</label>
      <select id="vehicle" name="vehicle" required>
        <option value="">Select vehicle type</option>
        <option value="Sedan">Sedan</option>
        <option value="SUV">SUV</option>
        <option value="Van">Van</option>
      </select>

      <label for="password">Password*</label>
      <input type="password" id="password" name="password" placeholder="Create a password" required>

      <button type="submit" class="register-btn" id="submitBtn">Register</button>
    </form>

    <a href="/index.php" class="back-link">← Back to Main</a>

  </div>

  <script>
    document.getElementById('registerForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const submitBtn = document.getElementById('submitBtn');
      const messageContainer = document.getElementById('message-container');
      
      // Disable button
      submitBtn.disabled = true;
      submitBtn.textContent = 'Registering...';
      
      // Clear previous messages
      messageContainer.innerHTML = '';
      
      // Get form data
      const formData = new FormData(this);
      
      try {
        const response = await fetch('customer-register-ajax.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          messageContainer.innerHTML = `<div class="msg-success">${result.success}</div>`;
          this.reset();
          
          // Redirect to login after 2 seconds
          setTimeout(() => {
            window.location.href = 'customer-login.php';
          }, 2000);
        } else if (result.error) {
          messageContainer.innerHTML = `<div class="msg-error">${result.error}</div>`;
          
          // Handle field errors
          if (result.fieldErrors) {
            for (const [field, error] of Object.entries(result.fieldErrors)) {
              const input = document.getElementById(field);
              if (input) {
                input.classList.add('input-error');
              }
            }
          }
        }
      } catch (error) {
        messageContainer.innerHTML = `<div class="msg-error">An error occurred. Please try again.</div>`;
        console.error('Error:', error);
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Register';
      }
    });
    
    // Remove error styling on input
    document.querySelectorAll('input, select').forEach(input => {
      input.addEventListener('input', function() {
        this.classList.remove('input-error');
      });
    });
  </script>

</body>
</html>

