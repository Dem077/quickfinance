<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Advance Form</title>
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
            margin-top: 30rem;
            border-collapse: collapse;
            margin-bottom: 10px;
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
           margin-top: 100rem;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            border: 1px solid #000;
            padding: 5px;
            height: 40px;
            width: 35%;
            
            font-size: 13px;
            text-align: left;
        } 
        .items-table td {
            border: 1px solid #000;
            padding: 5px;
            width: 65%;
            font-size: 15px;
            text-align: left;
        }
        .items-table th {
            background-color: #d6d6d6;
        }
        .footer-table {
            border: 1px solid #000;
            width: 100%;
            border-collapse: collapse;
        }
        .footer-table thead th{
            height: 30px;
            background-color: #d6d6d6;
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
        <h1>Local Advance Payment Request</h1>
        {{-- {{$record}} --}}
        <table class="header-content">
            <tr>
                <td class="logo-cell">
                    
                </td>
                <td class="details-cell">
                    <table class="details-table" >
                        <tr>
                            <td style="background-color: #d6d6d6; text-align:center;"><strong>Req No:</strong></td>
                            <td>{{$record->request_number}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #d6d6d6; text-align:center;"><strong>Date:</strong></td>
                            <td>{{$record->created_at->format('d-m-Y')}}</td>
                        </tr>
                        <tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table">
                <tr>
                    <th>SUPPLIER NAME</th>
                    <td>{{$record->vendor->name}}</td>
                </tr>
                <tr>
                    <th>ACCOUNT NUMBER</th>
                    <td>{{$record->vendor->account_no}}</td>
                </tr>
                <tr>
                    <th>PURCHASE ORDER NO.</th>
                    <td>{{$record->purchaseOrder->po_no}}</td>
                </tr>
                <tr>
                    <th>PURCHASE ORDER DATE</th>
                    <td>{{$record->purchaseOrder->date}}</td>
                </tr>
                <tr>
                    <th>PURCHASE ORDER TOTAL</th>
                    <td>{{$record->purchaseOrder->purchaseOrderDetails()->sum('amount')}}</td>
                </tr>
                <tr>
                    <th>QUOTATION NO</th>
                    <td>{{$record->qoation_no}}</td>
                </tr>
                <tr>
                    <th>EXPECTED DELIVERY</th>
                    <td>{{$record->expected_delivery}} Days</td>
                </tr>
                <tr>
                    <th>ADVANCE PAYMENT %</th>
                    <td>{{ number_format($record->advance_percentage, 0) }}%</td>
                </tr>
                <tr>
                    <th>ADVANCE PAYMENT AMOUNT</th>
                    <td>{{$record->advance_amount}}</td>
                </tr>
                <tr>
                    <th>BALANCE PAYMENT</th>
                    <td>{{$record->balance_amount}}</td>
                </tr>
                {{-- @foreach ($items as $item)
                   <tr> 
                    <td>{{$loop->iteration}}</td>
                    <td>{{$item->item->name}}</td>
                    <td>{{$record->budgetAccount->code}}</td>
                    <td>{{$item->unit}}</td>
                    <td>{{$item->amount}}</td>
                @endforeach --}}
        </table>

        <!--mpdf
    <htmlpagefooter name="custom-footer">
        <table class="footer-table">
            <thead>
                <tr>
                    <th>Prepared by:</th>
                    <th>Approved By:</th>
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
                    <td style="padding: 0%; text-align:center; width:33%; border-right: 1px solid #000"><strong>{{$record->user->name}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:33%; border-right: 1px solid #000"><strong>{{$record->user->department->hodfromusers->name}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:33%;"><strong></strong></td>
                </tr>
                <tr>
                    <td style="padding: 0%; text-align:center; height:30px; width:33%; border-right: 1px solid #000">{{$record->user->designation}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:33%; border-right: 1px solid #000">{{$record->user->department->hodfromusers->designation}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:33%;">Managing Director / DMD /GM</td>
                </tr>
        </table>
    </div>
      
    </htmlpagefooter>
    <sethtmlpagefooter name="custom-footer" value="on" />
    mpdf-->
</body>
</html>