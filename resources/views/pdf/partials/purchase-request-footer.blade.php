<div style="padding: 0 20px;">
    <table style="width: 100%; border: 2px solid #000; border-collapse: collapse; margin-bottom: 8px;">
        <tr>
            <td style="height: 22px; padding: 4px 6px;"><strong>Purpose:</strong> {{ $record->purpose }}</td>
        </tr>
    </table>
    <table style="border: 2px solid #000; width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="height: 28px; background-color: #b9b9b9; border: 1px solid #000;">Checked By:</th>
                <th style="height: 28px; background-color: #b9b9b9; border: 1px solid #000;">Budget Verified By:</th>
                <th style="height: 28px; background-color: #b9b9b9; border: 1px solid #000;">Approved By:</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="height: 85px; text-align: center; border-right: 1px solid #000; vertical-align: middle;">
                    @if($record->hodapprovedby?->signature)
                        <img src="{{ $record->hodapprovedby->signature }}" alt="Signature" style="width: 140px; height: auto; max-height: 75px;">
                    @endif
                </td>
                <td style="height: 85px; text-align: center; border-right: 1px solid #000; vertical-align: middle;">
                    @if($record->approvedby?->signature)
                        <img src="{{ $record->approvedby->signature }}" alt="Signature" style="width: 140px; height: auto; max-height: 75px;">
                    @endif
                </td>
                <td style="height: 85px; text-align: center; vertical-align: middle;">
                    @if($record->mdDmdApprovedBy?->signature)
                        <img src="{{ $record->mdDmdApprovedBy->signature }}" alt="Signature" style="width: 140px; height: auto; max-height: 75px;">
                    @endif
                </td>
            </tr>
            <tr>
                <td style="padding: 2px; text-align: center; width: 33%; border-right: 1px solid #000;"><strong>{{ $record->hodapprovedby?->name }}</strong></td>
                <td style="padding: 2px; text-align: center; width: 33%; border-right: 1px solid #000;"><strong>{{ $record->approvedby?->name }}</strong></td>
                <td style="padding: 2px; text-align: center; width: 33%;"><strong>{{ $record->mdDmdApprovedBy?->name }}</strong></td>
            </tr>
            <tr>
                <td style="padding: 2px; text-align: center; height: 22px; width: 33%; border-right: 1px solid #000;">{{ $record->hodapprovedby?->designation }}</td>
                <td style="padding: 2px; text-align: center; height: 22px; width: 33%; border-right: 1px solid #000;">{{ $record->approvedby?->designation }}</td>
                <td style="padding: 2px; text-align: center; height: 22px; width: 33%;">{{ $record->mdDmdApprovedBy?->designation }}</td>
            </tr>
        </tbody>
    </table>
    <div style="text-align: center; font-size: 10px; margin-top: 6px;">
        Page {PAGENO} of {nbpg}
    </div>
</div>
