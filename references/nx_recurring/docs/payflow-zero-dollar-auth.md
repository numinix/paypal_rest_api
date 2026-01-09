# Payflow Zero-Dollar Authorization

According to the *Payflow Gateway Developer Guide and Reference*, you can validate a card without placing a temporary charge by sending an **authorization (TRXTYPE=A)** request with **AMT=0.00**. Payflow treats that as a zero-dollar verification: the card is validated and a reference transaction ID (PNREF) is returned, but no funds are captured or held. This flow is the recommended way to store card details for future use without surprising the customer with a $1 test charge.
