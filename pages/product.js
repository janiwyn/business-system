// Barcode scanning logic for Add Product
(function() {
    const scanBtn = document.getElementById('scanProductBarcodeBtn');
    const scanModal = document.getElementById('productBarcodeScanModal');
    const closeScanBtn = document.getElementById('closeProductBarcodeScan');
    const scanVideo = document.getElementById('productBarcodeScanVideo');
    const scanCanvas = document.getElementById('productBarcodeScanCanvas');
    const rotateBtn = document.getElementById('rotateProductBarcodeCameraBtn');
    const scanModeSel = document.getElementById('productBarcodeScanMode');
    const scanStatus = document.getElementById('productBarcodeScanStatus');
    let currentStream = null;
    let currentFacing = 'environment';
    let scanActive = false;

    scanBtn?.addEventListener('click', () => {
        scanModal.style.display = 'flex';
        scanStatus.textContent = '';
        startCameraScan();
    });

    closeScanBtn?.addEventListener('click', () => {
        scanModal.style.display = 'none';
        stopCameraScan();
    });

    rotateBtn?.addEventListener('click', () => {
        currentFacing = (currentFacing === 'environment') ? 'user' : 'environment';
        startCameraScan();
    });

    scanModeSel?.addEventListener('change', () => {
        if (scanModeSel.value === 'hardware') {
            stopCameraScan();
            scanVideo.style.display = 'none';
            scanCanvas.style.display = 'none';
            scanStatus.textContent = 'Focus barcode input field and scan using hardware scanner.';
            ensureHardwareInput();
        } else {
            scanVideo.style.display = '';
            scanStatus.textContent = '';
            startCameraScan();
        }
    });

    function startCameraScan() {
        stopCameraScan();
        scanActive = true;
        scanVideo.style.display = '';
        scanCanvas.style.display = 'none';
        scanStatus.textContent = 'Initializing camera...';
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: currentFacing }
            }).then(stream => {
                currentStream = stream;
                scanVideo.srcObject = stream;
                scanVideo.play();
                scanStatus.textContent = 'Point camera at barcode.';
                if ('BarcodeDetector' in window) {
                    const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'upc_a', 'upc_e'] });
                    const scanFrame = () => {
                        if (!scanActive) return;
                        detector.detect(scanVideo).then(barcodes => {
                            if (barcodes.length > 0) {
                                handleBarcode(barcodes[0].rawValue);
                            } else {
                                requestAnimationFrame(scanFrame);
                            }
                        }).catch(() => requestAnimationFrame(scanFrame));
                    };
                    scanFrame();
                } else {
                    scanStatus.textContent = 'BarcodeDetector not supported. Please use Chrome/Edge or hardware scanner.';
                }
            }).catch(err => {
                scanStatus.textContent = 'Camera error: ' + err.message;
            });
        } else {
            scanStatus.textContent = 'Camera not supported.';
        }
    }

    function stopCameraScan() {
        scanActive = false;
        if (currentStream) {
            currentStream.getTracks().forEach(track => track.stop());
            currentStream = null;
        }
        scanVideo.srcObject = null;
    }

    function ensureHardwareInput() {
        let hwInput = document.getElementById('hardwareProductBarcodeInput');
        if (!hwInput) {
            hwInput = document.createElement('input');
            hwInput.type = 'text';
            hwInput.id = 'hardwareProductBarcodeInput';
            hwInput.style.position = 'absolute';
            hwInput.style.opacity = 0;
            hwInput.style.pointerEvents = 'none';
            scanModal.appendChild(hwInput);
        }
        hwInput.value = '';
        hwInput.focus();
        hwInput.oninput = function() {
            if (hwInput.value.length >= 6) {
                handleBarcode(hwInput.value.trim());
                hwInput.value = '';
            }
        };
    }

    function handleBarcode(barcode) {
        scanStatus.textContent = 'Barcode detected: ' + barcode;
        document.getElementById('barcode').value = barcode;
        scanModal.style.display = 'none';
        stopCameraScan();
        document.getElementById('name').focus();
    }

    scanModal?.addEventListener('click', function(e) {
        if (e.target === scanModal) {
            scanModal.style.display = 'none';
            stopCameraScan();
        }
    });
})();

// Product search filter for large device table
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearchInput');
    const table = document.getElementById('productTable');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const nameCell = row.querySelectorAll('td')[window.productNameColumnIndex || 1];
                if (nameCell) {
                    const name = nameCell.textContent.trim().toLowerCase();
                    row.style.display = (name.includes(filter) || filter === '') ? '' : 'none';
                }
            });
        });
    }
});

// Product search filter for small device table
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('productSearchInputMobile');
    const table = document.getElementById('productTableMobile');
    if (searchInput && table) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.trim().toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const nameCell = row.querySelectorAll('td')[window.productNameColumnIndex || 1];
                if (nameCell) {
                    const name = nameCell.textContent.trim().toLowerCase();
                    row.style.display = (name.includes(filter) || filter === '') ? '' : 'none';
                }
            });
        });
    }
});

// Restock button handler
document.addEventListener('DOMContentLoaded', function() {
    const restockModal = new bootstrap.Modal(document.getElementById('restockModal'));
    
    document.querySelectorAll('.restock-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const productId = this.dataset.id;
            const productName = this.dataset.name;
            const barcode = this.dataset.barcode;
            const branchId = this.dataset.branch;
            
            document.getElementById('restockProductId').value = productId;
            document.getElementById('restockProductName').textContent = 'Product: ' + productName;
            document.getElementById('restockBranchId').value = branchId;
            document.getElementById('restockQuantity').value = '';
            document.getElementById('restockMessage').innerHTML = '';
            
            // Fetch available stock from store
            try {
                const formData = new FormData();
                formData.append('action', 'get_store_stock');
                formData.append('barcode', barcode);
                formData.append('branch_id', branchId);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                document.getElementById('restockAvailable').value = data.stock || '0';
            } catch (e) {
                document.getElementById('restockAvailable').value = 'Error loading';
            }
            
            restockModal.show();
        });
    });
    
    document.getElementById('confirmRestock').addEventListener('click', async function() {
        const productId = document.getElementById('restockProductId').value;
        const branchId = document.getElementById('restockBranchId').value;
        const quantity = parseInt(document.getElementById('restockQuantity').value);
        const msgDiv = document.getElementById('restockMessage');
        
        if (!quantity || quantity <= 0) {
            msgDiv.innerHTML = '<div class="alert alert-warning">Please enter a valid quantity</div>';
            return;
        }
        
        this.disabled = true;
        this.textContent = 'Processing...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'restock_move');
            formData.append('shelf_product_id', productId);
            formData.append('branch_id', branchId);
            formData.append('quantity', quantity);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                msgDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                setTimeout(() => location.reload(), 1000);
            } else {
                msgDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                this.disabled = false;
                this.textContent = 'Confirm';
            }
        } catch (e) {
            msgDiv.innerHTML = '<div class="alert alert-danger">Error: ' + e.message + '</div>';
            this.disabled = false;
            this.textContent = 'Confirm';
        }
    });
    
    // Move to Shelf button handler
    const moveToShelfModal = new bootstrap.Modal(document.getElementById('moveToShelfModal'));
    
    document.querySelectorAll('.move-to-shelf-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const storeProductId = this.dataset.id;
            const productName = this.dataset.name;
            const availableStock = this.dataset.stock;
            
            document.getElementById('moveStoreProductId').value = storeProductId;
            document.getElementById('moveProductName').textContent = 'Product: ' + productName;
            document.getElementById('moveAvailableStock').value = availableStock;
            document.getElementById('moveQuantity').value = '';
            document.getElementById('moveMessage').innerHTML = '';
            
            moveToShelfModal.show();
        });
    });
    
    document.getElementById('confirmMoveToShelf').addEventListener('click', async function() {
        const storeProductId = document.getElementById('moveStoreProductId').value;
        const quantity = parseInt(document.getElementById('moveQuantity').value);
        const msgDiv = document.getElementById('moveMessage');
        
        if (!quantity || quantity <= 0) {
            msgDiv.innerHTML = '<div class="alert alert-warning">Please enter a valid quantity</div>';
            return;
        }
        
        this.disabled = true;
        this.textContent = 'Processing...';
        
        try {
            const formData = new FormData();
            formData.append('action', 'move_to_shelf');
            formData.append('store_product_id', storeProductId);
            formData.append('quantity', quantity);
            
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            // Get raw text first to debug
            const text = await response.text();
            console.log('Server response:', text); // DEBUG
            
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                console.error('JSON parse error:', parseErr);
                console.error('Response text:', text);
                msgDiv.innerHTML = '<div class="alert alert-danger">Server returned invalid response. Check console for details.</div>';
                this.disabled = false;
                this.textContent = 'Move to Shelf';
                return;
            }
            
            if (data.success) {
                msgDiv.innerHTML = '<div class="alert alert-success">' + data.message + '</div>';
                setTimeout(() => location.reload(), 1000);
            } else {
                msgDiv.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
                this.disabled = false;
                this.textContent = 'Move to Shelf';
            }
        } catch (e) {
            console.error('Request error:', e);
            msgDiv.innerHTML = '<div class="alert alert-danger">Error: ' + e.message + '</div>';
            this.disabled = false;
            this.textContent = 'Move to Shelf';
        }
    });
    
    // Search filters
    document.getElementById('storeSearchInput')?.addEventListener('input', function() {
        filterTable('storeProductsTable', this.value);
    });
    
    document.getElementById('shelfSearchInput')?.addEventListener('input', function() {
        filterTable('shelfProductsTable', this.value);
    });
    
    function filterTable(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    }
});
