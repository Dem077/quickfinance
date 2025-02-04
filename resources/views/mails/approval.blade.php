<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New PR Request</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap CSS CDN -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .mail-container {
            margin-top: 50px;
        }
        .logo {
            max-height: 80px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mail-container">
        <div class="card mx-auto" style="max-width: 600px;">
            <div class="card-header text-center mb-0 bg-white border-bottom-0">
                <img src="{{ asset('images\agro\agrologo.png') }}" alt="Company Logo" class="logo">
                <h5>Quick Finance</h5>
            </div>
            <div class="card-body text-center">
                <h4 class="card-title text-center">New {{$type ?? 'type'}} from {{ $requesterName ?? 'Unknown User' }} Pending Approval</h4>
                <p class="card-text text-left pl-5 mt-4">
                    <strong>{{$type ?? ''}} Number:</strong> {{ $typenumber ?? 'N/A' }}<br>
                    <strong>Description:</strong> {{ $typeDescription ?? 'N/A' }}<br>
                    <strong>Submitted by:</strong> {{ $typeDate ?? 'N/A' }}
                </p>
                <a href="{{ $typeUrl ?? '#' }}" class="btn btn-primary">View {{$type ?? 'type'}} Details</a>
            </div>
        </div>
    </div>
    <!-- Optional JavaScript -->
    <!-- jQuery and Bootstrap Bundle (includes Popper) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
</body>
</html>