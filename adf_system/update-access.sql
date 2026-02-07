-- Update business_access for all owner and admin users in all databases
UPDATE users 
SET business_access = '["bens-cafe","narayana-hotel","eat-meet","pabrik-kapal","furniture-jepara","karimunjawa-party-boat"]' 
WHERE role IN ('owner', 'admin');
