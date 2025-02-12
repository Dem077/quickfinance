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
            
            /* width: 200px; */
           
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
            /* width:20%; */
            border: 1px solid #000;
        }
        .details-table td:first-child {
            width:30%;  /* Control first column width */
        }

        .details-cell2 {
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .details-table2 {
            width: 60%;  /* Reduce width to 50% */
            text-align: left;
            border-collapse: collapse;
            margin: 0;   /* Remove any default margins */
            float: left; /* Align to left */
        }
        .details-table2 td {
            padding: 5px;
            width:100%;
            border: 1px solid #000;
        }
        .details-table2 td:first-child {
            width:50%;  /* Control first column width */
        }
        .name-cell { 
            text-align: center;
            /* padding-left: 270px; */
            padding-top: 30px;
            vertical-align: center;
        }
        .name-table {
            width: 100%;  /* Reduce width to 50% */
            border-collapse: collapse;
            margin: 0;   /* Remove any default margins */
            float: center; /* Align to left */
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
                <td class="name-cell"> 
                    <table class="name-table">
                        <tr>
                            <td><h1>Petty Cash Reimbursement</h1></td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
       
        {{-- {{$record}} --}}
        <table class="header-content">
            <tr>
                <td class="logo-cell details-cell2">
                    <table  class="details-table2">
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Date:</strong></td>
                            <td >{{ \Carbon\Carbon::parse($record->date)->format('d-M-Y') }}</td>
                        </tr>
                        <tr> 
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Department:</strong></td>
                            <td >{{$record->user->department->name??'-'}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Custodian:</strong></td>
                            <td >{{$record->user->name??'N/A'}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Account Name:</strong></td>
                            <td>{{$record->user->bank_account_name??'N/A'}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Account Number:</strong></td>
                            <td >{{$record->user->bank_account_no??'N/A'}}</td>
                        </tr>
                    </table>
                </td>
                <td class="details-cell">
                    <table class="details-table" >
                     
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Float Amount:</strong></td>
                            <td>{{$record->user->department->petty_cash_float_amount??'N/A'}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Amount Spent</strong></td>
                            <td>{{$items->sum('amount')??'N/A'}}</td>
                        </tr>
                        <tr>
                            <td style="background-color: #b9b9b9; text-align:center;"><strong>Balance</strong></td>
                            <td>{{$record->user->department->petty_cash_float_amount-$items->sum('amount')??'N/A'}}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Supplier Name</th>
                    <th>Bill No</th>
                    <th>Details</th>
                    <th>Refered Documents</th>
                    <th>Budget Code</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                   <tr> 
                        <td>{{$loop->iteration??'N/A'}}</td>
                        <td>{{\Carbon\Carbon::parse($item->date)->format('d-m-Y')??'N/A'}}</td>
                        <td>{{$item->vendor->name??'N/A'}}</td>
                        <td>{{$item->bill_no??'N/A'}}</td>
                        <td>{{$item->details??'N/A'}}</td>
                        <td>{{$item->purchaseOrder->po_no??'-'}}</td>
                        <td>{{$item->subBudget->code??'N/A'}}</td>
                        <td>{{$item->amount??'N/A'}}</td>
                   </tr>
                @endforeach
        </table>

        <!--mpdf
    <htmlpagefooter name="custom-footer">
        <table class="footer-table">
            <thead>
                <tr>
                    <th>Prepared By:</th>
                    <th>Reviewed By:</th>
                    <th>Verified By:</th>
                    <th>Approved By:</th>

                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding: 0%; text-align:center; width:25%;  height:30px; border-right: 1px solid #000"><strong>{{$record->user->name ?? 'N/A'}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:25%;  height:30px; border-right: 1px solid #000"><strong>{{$record->user->department->hodfromusers->name?? 'N/A'}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:25%;  height:30px; border-right: 1px solid #000"><strong>{{$record->VerifiedBy->name?? 'N/A'}}</strong></td>
                    <td style="padding: 0%; text-align:center; width:25%;  height:30px;"><strong>{{$record->ApprovedBy->name?? 'N/A'}}</strong></td>

                </tr>
                <tr>
                    <td style="padding: 0%; text-align:center; height:30px; width:25%; border-right: 1px solid #000">{{$record->user->designation?? 'N/A'}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:25%; border-right: 1px solid #000">{{$record->user->department->hodfromusers->designation?? 'N/A'}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:25%; border-right: 1px solid #000">{{$record->VerifiedBy->designation?? 'N/A'}}</td>
                    <td style="padding: 0%; text-align:center; height:30px; width:25%;">{{$record->VerifiedBy->designation?? 'N/A'}}</td>

                </tr>
            </tbody>
        
        </table>
            <table style="width: 100%; margin-top: 20px; text-align: center;">
                <tr>
                    <td> This is an Electronically Generated file no signature required</td>
                </tr>
            </table>
    </div>
      
    </htmlpagefooter>
    <sethtmlpagefooter name="custom-footer" value="on" />
    mpdf-->
</body>
</html>