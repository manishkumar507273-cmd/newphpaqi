const fs = require('fs');

async function testApprove() {
    console.log("--- Testing approve_request.php ---");
    const t0 = performance.now();
    const res = await fetch('http://localhost:8001/api/approve_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            id: 'req_6ad386042fccc',
            email: 'mb507273@gmail.com',
            name: 'Manish B'
        })
    });
    const t1 = performance.now();
    const data = await res.json();
    console.log("Status:", res.status);
    console.log("Response:", data);
    console.log("Approval roundtrip time:", Math.round(t1 - t0), "ms");
}

async function testSubmit() {
    console.log("\n--- Testing submit_request.php ---");
    const t0 = performance.now();
    const res = await fetch('http://localhost:8001/api/submit_request.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: 'Speed Benchmark User',
            email: 'mb507273@gmail.com',
            org: 'Benchmarking Unit',
            industry: 'Research',
            id_number: 'TEST-999',
            proof_name: 'test_proof.pdf',
            proof_data: 'data:application/pdf;base64,JVBERi0xLjQKJcTl8uXr',
            use_case: 'This is an automated performance test to verify that data request submission responds in less than 200 milliseconds!',
            scope: 'AQI Telemetry — 2026-10-16 (12:00 AM – 12:00 AM) [Previous Day]',
            target_date: '2026-10-16',
            telemetry_type: 'aqi'
        })
    });
    const t1 = performance.now();
    const data = await res.json();
    console.log("Status:", res.status);
    console.log("Response:", data);
    console.log("Submission roundtrip time:", Math.round(t1 - t0), "ms");
    return data.request_id;
}

async function run() {
    await testApprove();
    const newId = await testSubmit();
    if (newId) {
        console.log("\n--- Approving the newly created test request ---");
        const t0 = performance.now();
        const res = await fetch('http://localhost:8001/api/approve_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id: newId,
                email: 'mb507273@gmail.com',
                name: 'Speed Benchmark User'
            })
        });
        const t1 = performance.now();
        const data = await res.json();
        console.log("Status:", res.status);
        console.log("Response:", data);
        console.log("New Request Approval roundtrip time:", Math.round(t1 - t0), "ms");
    }
}

run();
