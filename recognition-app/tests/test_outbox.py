import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from src.queue.outbox import Outbox


def test_enqueue_and_pending(tmp_path):
    outbox = Outbox(str(tmp_path / "queue.sqlite3"))

    outbox.enqueue(student_id=1, confidence=0.9)
    outbox.enqueue(student_id=2, confidence=0.8)

    pending = outbox.pending()

    assert [(row[1], row[2]) for row in pending] == [(1, 0.9), (2, 0.8)]


def test_remove(tmp_path):
    outbox = Outbox(str(tmp_path / "queue.sqlite3"))
    outbox.enqueue(student_id=1, confidence=0.9)
    row_id = outbox.pending()[0][0]

    outbox.remove(row_id)

    assert outbox.pending() == []


def test_persists_across_instances(tmp_path):
    db_path = str(tmp_path / "queue.sqlite3")

    Outbox(db_path).enqueue(student_id=5, confidence=0.6)
    reopened = Outbox(db_path)

    assert len(reopened.pending()) == 1
