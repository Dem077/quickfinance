<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Purchase Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            padding: 20px;
        }
        h1 {
            text-align: center;
            font-size: 25px;
            margin: 0 0 20px 0;
        }
        .header-content {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
       
        .logo-cell {
            
            width: 120px;
           
        }
        .logo {
            padding: 0;
            width: 120px;
            height: auto;
        }
        .details-cell {
            padding: 10px;
            text-align: right;
            vertical-align: top;
        }
        .details-table {
            width: 60%;  /* Reduce width to 50% */
            text-align: left;
            border-collapse: collapse;
            margin: 0;   /* Remove any default margins */
            float: right; /* Align to left */
        }
        .details-table td {
            padding: 5px;
            border: 1px solid #000;
        }
        .details-table td:first-child {
            width: 30%;  /* Control first column width */
        }
        .name-cell { 
            /* text-align: center; */
            padding-left: 60px;
            padding-top: 30px;
            vertical-align: center;
        }
        .name-table {
            width: 100%;  /* Reduce width to 50% */
            border-collapse: collapse;
            margin: 0;   /* Remove any default margins */
            float: right; /* Align to left */
            margin-bottom: 10px;
        }
        .name-table td {
            padding: 5px;
        }
        .name-table td:first-child {
            width: 30%;  /* Control first column width */
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th, 
        .items-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
        }
        .items-table th {
            background-color: #b9b9b9;
        }
        .footer-table {
            border: 2px solid #000;
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table thead th{
            height: 30px;
            background-color: #b9b9b9;
            border: 1px solid #000;
        }
        .footer-table td {
            padding: 5px;
        }
    </style>
</head>
<body>
    <!--mpdf
    <htmlpageheader name="custom-header">
            <div class="header">
               <img class="logo" src="images/agro/agrologo.png" alt="Logo">
            </div>
        </htmlpageheader>
    <sethtmlpageheader name="custom-header" value="on" show-this-page="1" />
    mpdf-->
    <div class="container">
        <table style="width: 100%">
            <tr>
                <td class="logo-cell" >
                    
                </td>
                <td class="name-cell"> 
                    <table class="name-table">
                        <tr>
                            <td><h1>PURCHASE REQUEST</h1></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
       
        {{-- {{$record}} --}}
        <table class="header-content">
            <tr>
                <td class="logo-cell">
                    
                </td>
                <td class="details-cell">
                    <table class="details-table" >
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>PR No:</strong></td>
                            <td>{{$record->pr_no}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Date:</strong></td>
                            <td>{{$record->date}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Requested By:</strong></td>
                            <td>{{$record->user->name}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Department:</strong></td>
                            <td>{{$record->user->Department->name}}</td>
                        </tr>
                        @if($record->project )
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Project:</strong></td>
                            <td>{{$record->project->name}}</td>
                        </tr>
                        @endif
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Location:</strong></td>
                            <td>{{$record->location->name}}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item / Service</th>
                    <th>BUDGET CODE</th>
                    <th>UOM</th>
                    <th>QTY</th>
                    <th>EST COST</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                   <tr> 
                        <td>{{$loop->iteration}}</td>
                        <td>{{$item->items->name}}</td>
                        <td>{{$item->budgetAccount->code}}</td>
                        <td>{{$item->unit}}</td>
                        <td>{{$item->amount}}</td>
                        <td>{{$item->est_cost}}</td>
                   </tr>
                @endforeach
                <tr>
                    <td colspan="5" style="text-align: right; "><strong>Total</strong></td>
                    
                    <td><strong>{{$items->sum('est_cost')}}</strong></td>
                </tr>
        </table>

        <!--mpdf
    <htmlpagefooter name="custom-footer">
        <table style="width: 100%; border: 2px solid #000; margin-bottom: 20px; ">
            <tr>
                <td style="height: 20px;"><strong>Purpose:</strong>  {{$record->purpose}}</td>
            </tr>
        </table>
        <table class="footer-table">
            <thead>
                <tr>
                    <th>Checked By:</th>
                    <th>Budget Verified By:</th>
                    <th>Approved By:</th>
                </tr>
            </thead>
            <tbody>
                <tr > 
                    <td style="height: 70px; border-right: 1px solid #000"></td>
                    <td style="height: 70px; border-right: 1px solid #000"></td>
                    <td style="height: 70px;"></td>
                </tr>
                <tr>
                    <td style="padding: 0%; text-align:center; width:33%; border-right: 1px solid #000"><strong>{{$record->user->department->hodfromusers->name}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:33%; border-right: 1px solid #000"><strong>{{$record->approvedby->name}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:33%;"><strong></strong></td>
                </tr>
                <tr>
                    <td style="padding: 0%; text-align:center; height:30px; width:33%; border-right: 1px solid #000">{{$record->user->department->hodfromusers->designation}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:33%; border-right: 1px solid #000">{{$record->approvedby->designation}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:33%;">Managing Director / DMD /GM</td>
                </tr>
        </table>
    </div>
      
    </htmlpagefooter>
    <sethtmlpagefooter name="custom-footer" value="on" />
    mpdf-->
</body>
</html>