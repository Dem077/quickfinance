
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Status Update</title>
    <!--[if gte mso 9]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:AllowPNG/>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border: 1px solid #cccccc;">
                    <!-- ...existing code... -->
                    <tr>
                        <td style="padding: 20px 30px 40px 30px;">
                            <!-- ...existing code... -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 20px; text-align: center;">
                                <tr>
                                    <td>
                                        <p style="margin: 0 0 10px 10px; font-size:15px font-family: Arial, sans-serif; text-align: center; font-weight: bold;">
                                            <h1>{{env('APP_NAME')}}</h1>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    @if($status === 'approved')
                                        <td bgcolor="#d4edda" style="padding: 10px; border-radius: 4px; background-color: #d4edda !important; color: #155724 !important; border: 1px solid #c3e6cb; text-align: center;">
                                            <!--[if gte mso 9]>
                                            <v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:540px;">
                                                <v:fill type="tile" color="#d4edda" />
                                                <v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
                                            <![endif]-->
                                           
                                            <p style="margin: 0 0 20px 0; font-family: Arial, sans-serif; text-align: center;">Your {{ $type ?? ''}} has been Approved @if($by)by {{$by}}@endif</p>
                                            {{-- <p style="margin: 0; font-family: Arial, sans-serif; text-align: center; color: #155724;">Status: {{ ucfirst($status) }}</p> --}}
                                            <!--[if gte mso 9]>
                                                </v:textbox>
                                            </v:rect>
                                            <![endif]-->
                                        </td>
                                    @else
                                        <td bgcolor="#f8d7da" style="padding: 10px; border-radius: 4px; text-align: center; background-color: #f8d7da !important; !important; border: 1px solid #f5c6cb;">
                                            <!--[if gte mso 9]>
                                            <v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:540px;">
                                                <v:fill type="tile" color="#f8d7da" />
                                                <v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0">
                                            <![endif]-->
                                            <p style="margin: 0 0 20px 0; font-family: Arial, sans-serif; text-align: center;">You are {{ $type ?? ''}} has been Rejected</p>
                                            {{-- <p style="margin: 0; font-family: Arial, sans-serif; text-align: center;">Status: {{ ucfirst($status) }}</p> --}}
                                            <!--[if gte mso 9]>
                                                </v:textbox>
                                            </v:rect>
                                            <![endif]-->
                                        </td>
                                    @endif
                                </tr>
                            </table>
                            <!-- ...rest of existing code... -->
                     
                            @if($reason)
                                <p style="margin: 0 0 20px 0; font-family: Arial, sans-serif;"><strong>Reason:</strong> {{ $reason ?? ''}}</p>
                            @endif

                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto; text-align: center;">
                                <tr>
                                    <td style="border-radius: 4px; background: #155724; text-align: center;">
                                      <a href="{{env('APP_URL')}}" style="background: #155724; border: 15px solid #155724; font-family: Arial, sans-serif; font-size: 14px; line-height: 1.1; text-align: center; text-decoration: none; display: block; border-radius: 4px; font-weight: bold; color: #ffffff;">View Details</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px; border-top: 1px solid #cccccc;">
                            <p style="margin: 0; font-size: 12px; line-height: 20px; font-family: Arial, sans-serif; color: #666666; text-align: center;">
                                This is an automated email. Please do not reply.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>