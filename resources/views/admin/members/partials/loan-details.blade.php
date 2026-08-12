<!-- Loan Details Section - Displays all loan cycles, installments, and security transactions -->
<div class="grid grid-cols-1 gap-6 mt-6">
    @forelse($member->loans as $loan)
        <!-- Loan Header Card -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg border border-blue-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">💰 Mkopo No. {{ $loan->loan_number }}</h3>
                    <p class="text-sm text-gray-600">{{ $loan->product?->name ?? 'Loan Product' }}</p>
                </div>
                <span
                    class="px-4 py-2 rounded-full text-sm font-semibold 
                    {{ $loan->status->value === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                    {{ ucfirst($loan->status->value) }}
                </span>
            </div>

            <!-- Quick Loan Summary -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-3 rounded">
                    <p class="text-xs text-gray-600">Kiasi cha Mkopo (Principal)</p>
                    <p class="text-lg font-bold text-gray-900">{{ number_format($loan->principal_amount, 2) }}</p>
                </div>
                <div class="bg-white p-3 rounded">
                    <p class="text-xs text-gray-600">Kiwango cha Riba (Interest %)</p>
                    <p class="text-lg font-bold text-gray-900">{{ number_format($loan->interest_rate, 2) }}%</p>
                </div>
                <div class="bg-white p-3 rounded">
                    <p class="text-xs text-gray-600">Salio (Balance)</p>
                    <p class="text-lg font-bold text-indigo-600">{{ number_format($loan->total_balance, 2) }}</p>
                </div>
                <div class="bg-white p-3 rounded">
                    <p class="text-xs text-gray-600">Tarehe ya Kupewa (Disbursement)</p>
                    <p class="text-sm font-semibold text-gray-900">
                        {{ $loan->disbursement_date?->format('M d, Y') ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Loan Cycles Section -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-bold text-gray-900">📊 Awamu ya Mkopo (Loan Cycles)</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Aina (Type)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Jina la Biashara
                                (Business)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Kiasi (Amount)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Riba (%)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Tarehe (Date)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Rejesho/Wiki</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Hali (Status)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($loan->cycles as $cycle)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <span
                                        class="px-3 py-1 rounded-full text-xs font-semibold {{ $cycle->is_main_cycle ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                        {{ $cycle->is_main_cycle ? 'Main' : 'Refinancing' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-medium text-gray-900">{{ $cycle->business_name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 text-gray-900">{{ number_format($cycle->principal_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 text-gray-900">{{ number_format($cycle->interest_rate, 2) }}%</td>
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $cycle->disbursement_date?->format('M d, Y') ?? 'N/A' }}</td>
                                <td class="px-6 py-4 text-gray-900">{{ number_format($cycle->weekly_installment, 2) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="px-2 py-1 rounded text-xs font-semibold {{ $cycle->status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ ucfirst($cycle->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No loan cycles recorded
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Add New Cycle Button -->
            <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                <button type="button" class="text-indigo-600 hover:text-indigo-700 font-semibold text-sm"
                    onclick="document.getElementById('cycle-form-{{ $loan->id }}').classList.toggle('hidden')">
                    + Add Loan Cycle
                </button>
            </div>

            <!-- Add Cycle Form (Hidden by default) -->
            <div id="cycle-form-{{ $loan->id }}" class="hidden bg-gray-50 px-6 py-4 border-t border-gray-200">
                <form action="{{ route('admin.loans.cycles.store', $loan) }}" method="POST"
                    class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Jina la Biashara *</label>
                        <input type="text" name="business_name"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Aina ya Awamu *</label>
                        <select name="cycle_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                            <option value="main">Main Cycle</option>
                            <option value="refinancing">Refinancing Cycle</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Kiasi cha Mkopo Mkuu *</label>
                        <input type="number" name="principal_amount" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Kiwango cha Riba (%) *</label>
                        <input type="number" name="interest_rate" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tarehe ya Kupewa *</label>
                        <input type="date" name="disbursement_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tarehe ya Rejesho la Kwanza
                            *</label>
                        <input type="date" name="first_payment_date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ada ya Uanachama *</label>
                        <input type="number" name="admission_fee" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" value="0">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Ada ya Mkopo *</label>
                        <input type="number" name="processing_fee" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" value="0">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Gharama ya Miamala *</label>
                        <input type="number" name="transaction_charges" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" value="0">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Rejesho la Kila Wiki *</label>
                        <input type="number" name="weekly_installment" step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Jumla ya Marejesho *</label>
                        <input type="number" name="total_installments"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg" value="26" required>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition">
                            Save Loan Cycle
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Installment Records Section -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-bold text-gray-900">📋 Marejesho ya Kila Wiki (Weekly Installments)</h4>
            </div>
            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Rejesho No</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Tarehe (Date)</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Kiasi cha Mkopo</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Riba</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Jumla</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Salio</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Hali</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($loan->installmentRecords()->orderBy('installment_number')->get() as $installment)
                            <tr class="hover:bg-gray-50 {{ $installment->is_paid ? 'bg-green-50' : '' }}">
                                <td class="px-6 py-4 font-semibold text-gray-900">
                                    #{{ $installment->installment_number }}</td>
                                <td class="px-6 py-4 text-gray-600">{{ $installment->payment_date->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-right text-gray-900">
                                    {{ number_format($installment->principal_amount, 2) }}</td>
                                <td class="px-6 py-4 text-right text-gray-900">
                                    {{ number_format($installment->interest_amount, 2) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                    {{ number_format($installment->total_amount, 2) }}</td>
                                <td class="px-6 py-4 text-right text-gray-900">
                                    {{ number_format($installment->outstanding_balance, 2) }}</td>
                                <td class="px-6 py-4">
                                    @if ($installment->is_paid)
                                        <span
                                            class="px-3 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">Paid</span>
                                    @elseif(now()->isAfter($installment->payment_date))
                                        <span
                                            class="px-3 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">Overdue</span>
                                    @else
                                        <span
                                            class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded text-xs font-semibold">Pending</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">No installment records
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Security Account Section -->
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-bold text-gray-900">🔒 Akaunti ya Usalama (Security Account)</h4>
                <p class="text-sm text-gray-600 mt-1">Salio Lililobaki: <span
                        class="font-bold text-indigo-600">{{ number_format($loan->current_security_balance, 2) }}</span>
                </p>
            </div>
            <div class="overflow-x-auto max-h-72">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 border-b sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Tarehe</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Kiasi cha Akiba</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Kilichotolewa</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700">Salio</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700">Sahihi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($loan->securityTransactions()->orderBy('transaction_date', 'desc')->get() as $transaction)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-gray-600">
                                    {{ $transaction->transaction_date->format('M d, Y') }}</td>
                                <td
                                    class="px-6 py-4 text-right {{ ($transaction->security_amount ?? 0) > 0 ? 'text-green-600 font-semibold' : 'text-gray-600' }}">
                                    {{ number_format($transaction->security_amount ?? 0, 2) }}
                                </td>
                                <td
                                    class="px-6 py-4 text-right {{ ($transaction->withdrawal_amount ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-gray-600' }}">
                                    {{ number_format($transaction->withdrawal_amount ?? 0, 2) }}
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                                    {{ number_format($transaction->balance, 2) }}</td>
                                <td class="px-6 py-4">
                                    <span class="text-xs text-gray-500">
                                        {{ $transaction->collectedBy?->name ?? 'System' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">No security
                                    transactions</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @empty
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 text-center">
            <p class="text-gray-600">No loans found for this member</p>
        </div>
    @endforelse
</div>
