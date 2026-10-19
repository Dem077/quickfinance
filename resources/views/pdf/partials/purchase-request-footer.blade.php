<div style="padding: 0 20px;">
    <table style="width: 100%; border: 2px solid #000; border-collapse: collapse; margin-bottom: 6px;">
        <tr>
            <td style="padding: 3px 6px;"><strong>Purpose:</strong> {{ $record->purpose }}</td>
        </tr>
    </table>
    <table style="border: 2px solid #000; width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="padding: 4px; background-color: #b9b9b9; border: 1px solid #000;">Checked By:</th>
                <th style="padding: 4px; background-color: #b9b9b9; border: 1px solid #000;">Budget Verified By:</th>
                <th style="padding: 4px; background-color: #b9b9b9; border: 1px solid #000;">Approved By:</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 2px 4px; text-align: center; border-right: 1px solid #000; vertical-align: middle;">
                    @if($record->hodapprovedby?->signature)
                        <img src="{{ $record->hodapprovedby->signature }}" alt="Signature" width="200" height="72" style="width: 200px; height: 72px;">
                    @endif
                </td>
                <td style="padding: 2px 4px; text-align: center; border-right: 1px solid #000; vertical-align: middle;">
                    @if($record->approvedby?->signature)
                        <img src="{{ $record->approvedby->signature }}" alt="Signature" width="200" height="72" style="width: 200px; height: 72px;">
                    @endif
                </td>
                <td style="padding: 2px 4px; text-align: center; vertical-align: middle;">
                    @if($record->mdDmdApprovedBy?->signature)
                        <img src="{{ $record->mdDmdApprovedBy->signature }}" alt="Signature" width="200" height="72" style="width: 200px; height: 72px;">
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 1px 2px; text-align: center; width: 33%; border-right: 1px solid #000;"><strong>{{ $record->hodapprovedby?->nameWithEmployeeNum() }}</strong></td>
                <td style="padding: 1px 2px; text-align: center; width: 33%; border-right: 1px solid #000;"><strong>{{ $record->approvedby?->nameWithEmployeeNum() }}</strong></td>
                <td style="padding: 1px 2px; text-align: center; width: 33%;"><strong>{{ $record->mdDmdApprovedBy?->nameWithEmployeeNum() }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 1px 2px; text-align: center; width: 33%; border-right: 1px solid #000;">{{ $record->hodapprovedby?->designation }}</td>
                <td style="padding: 1px 2px; text-align: center; width: 33%; border-right: 1px solid #000;">{{ $record->approvedby?->designation }}</td>
                <td style="padding: 1px 2px; text-align: center; width: 33%;">{{ $record->mdDmdApprovedBy?->designation }}</td>
            </tr>
        </tbody>
    </table>
    <div style="text-align: center; font-size: 10px; margin-top: 4px;">
        Page {PAGENO} of {nbpg}
    </div>
</div>
